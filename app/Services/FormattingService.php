<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyLocaleSetting;
use App\Models\Currency;
use App\Support\Money\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use IntlDateFormatter;
use NumberFormatter;

class FormattingService
{
    private const DEFAULT_LOCALE = 'en';

    private const DEFAULT_SCALE = 6;

    public function __construct(private readonly LocaleService $localeService)
    {
    }

    public function formatNumber(BigDecimal|float|int $value, Company $company): string
    {
        $decimal = $value instanceof BigDecimal
            ? $value->toScale(self::DEFAULT_SCALE, RoundingMode::HALF_UP)
            : BigDecimal::of((string) $value)->toScale(self::DEFAULT_SCALE, RoundingMode::HALF_UP);

        $setting = $this->localeService->getForCompany($company);
        $locale = $this->resolveNumberLocale($setting);

        $scale = $this->detectScale($decimal);

        if (! class_exists('NumberFormatter')) {
            return number_format((float) $decimal->__toString(), max($scale, 0), '.', ',');
        }

        $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $scale);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, max($scale, self::DEFAULT_SCALE));

        $formatted = $formatter->format((float) $decimal->__toString());

        if ($formatted === false) {
            return $decimal->__toString();
        }

        return $formatted;
    }

    public function formatDate(Carbon $date, Company $company): string
    {
        $setting = $this->localeService->getForCompany($company);
        $locale = $setting->locale ?: self::DEFAULT_LOCALE;
        $timezone = $setting->timezone ?: config('app.timezone');
        $pattern = $this->datePattern($setting->date_format);

        if (! class_exists('IntlDateFormatter')) {
            return $date->copy()->setTimezone($timezone)->format($pattern ?? 'M j, Y g:i A');
        }

        $formatter = new IntlDateFormatter(
            $locale,
            $pattern === null ? IntlDateFormatter::MEDIUM : IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $timezone,
            null,
            $pattern
        );

        $timestamp = $date->copy()->setTimezone($timezone)->getTimestamp();
        $formatted = $formatter->format($timestamp);

        if ($formatted === false) {
            return $date->copy()->setTimezone($timezone)->format('Y-m-d');
        }

        return $formatted;
    }

    public function formatMoney(Money $money, Company $company): string
    {
        $setting = $this->localeService->getForCompany($company);
        $locale = $this->resolveNumberLocale($setting);
        $currency = $money->currency();
        $minorUnit = $this->minorUnit($currency);

        if (! class_exists('NumberFormatter')) {
            return sprintf('%s %s', $currency, $money->format($minorUnit));
        }

        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $minorUnit);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $minorUnit);

        $divisor = BigDecimal::one();
        if ($minorUnit > 0) {
            $divisor = BigDecimal::of('1'.str_repeat('0', $minorUnit));
        }

        $decimalAmount = BigDecimal::of((string) $money->amountMinor())
            ->dividedBy($divisor, self::DEFAULT_SCALE, RoundingMode::HALF_UP);

        $formatted = $formatter->formatCurrency((float) $decimalAmount->__toString(), $currency);

        if ($formatted === false) {
            return $money->format($minorUnit);
        }

        return $formatted;
    }

    private function resolveNumberLocale(CompanyLocaleSetting $setting): string
    {
        if ($setting->number_format !== 'system') {
            return $setting->number_format;
        }

        return $setting->locale ?: self::DEFAULT_LOCALE;
    }

    private function detectScale(BigDecimal $decimal): int
    {
        $string = $decimal->__toString();

        if (! str_contains($string, '.')) {
            return 0;
        }

        $fraction = rtrim(explode('.', $string, 2)[1], '0');

        return min(strlen($fraction), 6);
    }

    private function datePattern(string $format): ?string
    {
        return match ($format) {
            'ISO' => 'yyyy-MM-dd',
            'DMY' => 'dd/MM/yyyy',
            'MDY' => 'MM/dd/yyyy',
            'YMD' => 'yyyy/MM/dd',
            default => null,
        };
    }

    private function minorUnit(string $currency): int
    {
        $cacheKey = 'currency_minor_unit:'.$currency;

        return Cache::rememberForever($cacheKey, function () use ($currency): int {
            $record = Currency::query()->where('code', $currency)->first();

            return $record?->minor_unit ?? 2;
        });
    }
}
