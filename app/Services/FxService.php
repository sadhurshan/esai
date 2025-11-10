<?php

namespace App\Services;

use App\Enums\MoneyRoundRule;
use App\Exceptions\FxRateNotFoundException;
use App\Models\Currency;
use App\Models\FxRate;
use App\Support\Money\Money;
use App\Support\Audit\AuditLogger;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class FxService
{
    private const CACHE_TTL_SECONDS = 3600;
    private const RATE_SCALE = 8;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function getRate(string $base, string $quote, ?Carbon $date = null): string
    {
        $base = Str::upper($base);
        $quote = Str::upper($quote);

        if ($base === $quote) {
            return $this->formatRate('1');
        }

        $dateKey = $date?->toDateString() ?? 'latest';
        $cacheKey = $this->cacheKey($base, $quote, $dateKey);

        return $this->cache->remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($base, $quote, $date): string {
            $asOf = $date?->toDateString();

            $direct = $this->findRate($base, $quote, $asOf);
            if ($direct !== null) {
                return $direct;
            }

            $inverse = $this->findRate($quote, $base, $asOf);
            if ($inverse !== null) {
                $rate = 1 / (float) $inverse;

                return $this->formatRate((string) $rate);
            }

            throw new FxRateNotFoundException("FX rate {$base}/{$quote} not found.");
        });
    }

    public function convert(Money $money, string $toCurrency, Carbon $asOf, MoneyRoundRule $rule = MoneyRoundRule::HalfUp): Money
    {
        $fromCurrency = Str::upper($money->currency());
        $targetCurrency = Str::upper($toCurrency);

        if ($fromCurrency === $targetCurrency) {
            return Money::fromMinor($money->amountMinor(), $targetCurrency);
        }

        $rate = (float) $this->getRate($fromCurrency, $targetCurrency, $asOf);

        $fromMinorUnit = $this->minorUnit($fromCurrency);
        $toMinorUnit = $this->minorUnit($targetCurrency);

        $baseAmount = $money->amountMinor() / (10 ** $fromMinorUnit);
        $converted = $baseAmount * $rate;
        $convertedMinor = $converted * (10 ** $toMinorUnit);

        $roundedMinor = $this->roundFloat($convertedMinor, $rule);

        return Money::fromMinor($roundedMinor, $targetCurrency);
    }

    /**
     * @param array<int, array{base_code:string, quote_code:string, rate:string, as_of:string}> $rows
     * @return array<int, FxRate>
     */
    public function upsertDailyRates(array $rows): array
    {
        $results = [];

        foreach ($rows as $row) {
            $base = Str::upper(Arr::get($row, 'base_code'));
            $quote = Str::upper(Arr::get($row, 'quote_code'));
            $rate = $this->formatRate((string) Arr::get($row, 'rate'));
            $asOf = Carbon::parse(Arr::get($row, 'as_of'))->toDateString();

            $existing = FxRate::query()
                ->where('base_code', $base)
                ->where('quote_code', $quote)
                ->whereDate('as_of', $asOf)
                ->first();

            if ($existing !== null) {
                $before = $existing->getOriginal();
                $existing->rate = $rate;

                if ($existing->isDirty()) {
                    $existing->save();
                    $this->auditLogger->updated($existing, $before, $existing->toArray());
                }

                $results[] = $existing;
            } else {
                $model = FxRate::create([
                    'base_code' => $base,
                    'quote_code' => $quote,
                    'rate' => $rate,
                    'as_of' => $asOf,
                ]);

                $this->auditLogger->created($model, $model->toArray());

                $results[] = $model;
            }

            $this->invalidateCache($base, $quote, $asOf);
        }

        return $results;
    }

    private function findRate(string $base, string $quote, ?string $asOf): ?string
    {
        $query = FxRate::query()
            ->where('base_code', $base)
            ->where('quote_code', $quote);

        if ($asOf !== null) {
            $rate = (clone $query)
                ->whereDate('as_of', '<=', $asOf)
                ->orderByDesc('as_of')
                ->orderByDesc('id')
                ->first();

            if ($rate !== null) {
                return $this->formatRate((string) $rate->rate);
            }
        }

        $latest = $query
            ->orderByDesc('as_of')
            ->orderByDesc('id')
            ->first();

        return $latest ? $this->formatRate((string) $latest->rate) : null;
    }

    private function minorUnit(string $currency): int
    {
        $cacheKey = 'currency_minor_unit:'.$currency;

        return $this->cache->rememberForever($cacheKey, function () use ($currency): int {
            $record = Currency::query()->where('code', $currency)->first();

            if ($record === null) {
                throw new \RuntimeException("Currency {$currency} not configured.");
            }

            return (int) $record->minor_unit;
        });
    }

    private function cacheKey(string $base, string $quote, string $dateKey): string
    {
        return "fx_rate:{$base}:{$quote}:{$dateKey}";
    }

    private function formatRate(string $value): string
    {
        return number_format((float) $value, self::RATE_SCALE, '.', '');
    }

    private function roundFloat(float $value, MoneyRoundRule $rule): int
    {
        return match ($rule) {
            MoneyRoundRule::Bankers => (int) round($value, 0, PHP_ROUND_HALF_EVEN),
            MoneyRoundRule::HalfUp => (int) round($value, 0, PHP_ROUND_HALF_UP),
        };
    }

    private function invalidateCache(string $base, string $quote, string $asOf): void
    {
        $this->cache->forget($this->cacheKey($base, $quote, $asOf));
        $this->cache->forget($this->cacheKey($base, $quote, 'latest'));
        $this->cache->forget($this->cacheKey($quote, $base, $asOf));
        $this->cache->forget($this->cacheKey($quote, $base, 'latest'));
    }
}
