<?php

namespace App\Services;

use App\Enums\MoneyRoundRule;
use App\Enums\TaxRegime;
use App\Models\TaxCode;
use App\Support\Money\Money;
use Illuminate\Support\Collection;

class TaxService
{
    /**
     * @param array<int, int> $taxCodeIds
     */
    public function resolveCodes(int $companyId, array $taxCodeIds): Collection
    {
        if ($taxCodeIds === []) {
            return collect();
        }

        $idOrder = collect($taxCodeIds)
            ->filter(static fn ($value) => $value !== null)
            ->map(static fn ($value) => (int) $value)
            ->values();

        if ($idOrder->isEmpty()) {
            return collect();
        }

        $records = TaxCode::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $idOrder)
            ->where('active', true)
            ->orderBy('code')
            ->get();

        return $records
            ->filter(static fn (TaxCode $code) => $idOrder->contains($code->id))
            ->sortBy(fn (TaxCode $code) => $idOrder->search($code->id))
            ->values();
    }

    /**
     * @param iterable<int, TaxCode> $taxCodes
     * @return array{subtotal: Money, tax_total: Money, grand_total: Money, taxes: array<int, array{tax_code: TaxCode, amount: Money, amount_minor: int, rate_percent: float}>>
     */
    public function computeLineTaxes(
        Money $unitPrice,
        float $quantity,
        TaxRegime $regime,
        iterable $taxCodes,
        MoneyRoundRule $roundRule = MoneyRoundRule::HalfUp
    ): array {
        $currency = $unitPrice->currency();
    $codes = collect($taxCodes)->values();

        $lineAmount = $unitPrice->multiply($quantity, $roundRule);
        $lineMinor = $lineAmount->amountMinor();

        if ($codes->isEmpty()) {
            return [
                'subtotal' => $lineAmount,
                'tax_total' => Money::fromMinor(0, $currency),
                'grand_total' => $lineAmount,
                'taxes' => [],
            ];
        }

        if ($regime === TaxRegime::Exclusive) {
            $subtotalMinor = $lineMinor;
            $calculated = $this->calculateExclusiveTaxes($subtotalMinor, $codes, $roundRule);
            $taxTotalMinor = $calculated['tax_total_minor'];
            $grandMinor = $subtotalMinor + $taxTotalMinor;
        } else {
            [$subtotalMinor, $calculated] = $this->resolveInclusiveSubtotal($lineMinor, $codes, $roundRule);
            $taxTotalMinor = $calculated['tax_total_minor'];
            $grandMinor = $lineMinor;
        }

        $formattedTaxes = array_map(function (array $row) use ($currency) {
            return [
                'tax_code' => $row['tax_code'],
                'amount' => Money::fromMinor($row['amount_minor'], $currency),
                'amount_minor' => $row['amount_minor'],
                'rate_percent' => $row['rate_percent'],
            ];
        }, $calculated['taxes']);

        return [
            'subtotal' => Money::fromMinor($subtotalMinor, $currency),
            'tax_total' => Money::fromMinor($taxTotalMinor, $currency),
            'grand_total' => Money::fromMinor($grandMinor, $currency),
            'taxes' => $formattedTaxes,
        ];
    }

    /**
     * @param Collection<int, TaxCode> $taxCodes
     * @return array{taxes: array<int, array{tax_code: TaxCode, amount_minor: int, rate_percent: float}>, tax_total_minor: int}
     */
    private function calculateExclusiveTaxes(int $baseMinor, Collection $taxCodes, MoneyRoundRule $roundRule): array
    {
        $taxTotal = 0;
        $taxes = [];

        foreach ($taxCodes as $index => $taxCode) {
            $ratePercent = (float) ($taxCode->rate_percent ?? 0.0);
            $taxableMinor = $baseMinor;

            if ((bool) $taxCode->is_compound) {
                $taxableMinor += $taxTotal;
            }

            $rate = $ratePercent / 100;
            $amountMinor = $rate > 0 ? $this->roundFloat($taxableMinor * $rate, $roundRule) : 0;

            $taxTotal += $amountMinor;

            $taxes[] = [
                'tax_code' => $taxCode,
                'amount_minor' => $amountMinor,
                'rate_percent' => $ratePercent,
                'sequence' => $index + 1,
            ];
        }

        return [
            'taxes' => $taxes,
            'tax_total_minor' => $taxTotal,
        ];
    }

    /**
     * @param Collection<int, TaxCode> $taxCodes
     * @return array{0:int,1:array{taxes: array<int, array{tax_code: TaxCode, amount_minor: int, rate_percent: float}>, tax_total_minor: int}}
     */
    private function resolveInclusiveSubtotal(int $grossMinor, Collection $taxCodes, MoneyRoundRule $roundRule): array
    {
        if ($grossMinor <= 0) {
            return [0, [
                'taxes' => [],
                'tax_total_minor' => 0,
            ]];
        }

        $nonZeroRates = $taxCodes->filter(static fn (TaxCode $code) => (float) ($code->rate_percent ?? 0) > 0);

        if ($nonZeroRates->isEmpty()) {
            return [$grossMinor, [
                'taxes' => $taxCodes->map(fn (TaxCode $code) => [
                    'tax_code' => $code,
                    'amount_minor' => 0,
                    'rate_percent' => (float) ($code->rate_percent ?? 0.0),
                ])->values()->all(),
                'tax_total_minor' => 0,
            ]];
        }

        $low = 0;
        $high = $grossMinor;
        $bestNet = $grossMinor;
        $bestCalculation = [
            'taxes' => [],
            'tax_total_minor' => $grossMinor,
        ];

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $calculated = $this->calculateExclusiveTaxes($mid, $taxCodes, $roundRule);
            $grossCandidate = $mid + $calculated['tax_total_minor'];

            if ($grossCandidate === $grossMinor) {
                $bestNet = $mid;
                $bestCalculation = $calculated;
                break;
            }

            if ($grossCandidate < $grossMinor) {
                $bestNet = $mid;
                $bestCalculation = $calculated;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        $grossComputed = $bestNet + $bestCalculation['tax_total_minor'];
        $difference = $grossMinor - $grossComputed;

        if ($difference !== 0 && $bestCalculation['taxes'] !== []) {
            $index = array_key_last($bestCalculation['taxes']);
            $bestCalculation['taxes'][$index]['amount_minor'] += $difference;
            $bestCalculation['tax_total_minor'] += $difference;
        }

        return [$bestNet, $bestCalculation];
    }

    private function minorUnit(string $currency): int
    {
        $currency = Str::upper($currency);
        $cacheKey = 'currency_minor_unit:'.$currency;

        return $this->cache->rememberForever($cacheKey, function () use ($currency): int {
            $record = Currency::query()->where('code', $currency)->first();

            if ($record === null) {
                throw new \RuntimeException("Currency {$currency} not configured.");
            }

            return (int) $record->minor_unit;
        });
    }

    private function roundFloat(float $value, MoneyRoundRule $rule): int
    {
        return match ($rule) {
            MoneyRoundRule::Bankers => (int) round($value, 0, PHP_ROUND_HALF_EVEN),
            MoneyRoundRule::HalfUp => (int) round($value, 0, PHP_ROUND_HALF_UP),
        };
    }
}
