<?php

namespace App\Services;

use App\Enums\MoneyRoundRule;
use App\Enums\TaxRegime;
use App\Models\CompanyMoneySetting;
use App\Models\Currency;
use App\Support\Money\Money;
use Illuminate\Validation\ValidationException;

class TotalsCalculator
{
    public function __construct(private readonly TaxService $taxService)
    {
    }

    /**
     * @param array<int, array{quantity: float|int|string, unit_price?: float|int|string|null, unit_price_minor?: int|null, tax_code_ids?: array<int, int>, key?: int|string}> $lines
     * @return array{
     *     currency: string,
     *     minor_unit: int,
     *     round_rule: string,
     *     tax_regime: string,
     *     lines: array<int, array{
     *         index: int,
     *         key: int|string,
     *         quantity: float,
     *         unit_price_minor: int,
     *         subtotal_minor: int,
     *         tax_total_minor: int,
     *         grand_total_minor: int,
     *         taxes: array<int, array{tax_code_id: int, rate_percent: float, amount_minor: int, sequence: int}>
     *     }> ,
     *     totals: array{subtotal_minor: int, tax_total_minor: int, grand_total_minor: int}
     * }
     */
    public function calculate(int $companyId, string $currency, array $lines): array
    {
        $currency = strtoupper($currency);
        $minorUnit = $this->resolveMinorUnit($currency);
        $settings = CompanyMoneySetting::query()->where('company_id', $companyId)->first();

        $roundRule = $settings?->price_round_rule
            ? MoneyRoundRule::from($settings->price_round_rule)
            : MoneyRoundRule::HalfUp;

        $taxRegime = $settings?->tax_regime
            ? TaxRegime::from($settings->tax_regime)
            : TaxRegime::Exclusive;

        $lineResults = [];
        $subtotalMinor = 0;
        $taxTotalMinor = 0;
        $grandTotalMinor = 0;

        foreach ($lines as $index => $line) {
            $quantity = (float) ($line['quantity'] ?? 0);

            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}.quantity" => ['Quantity must be greater than zero.'],
                ]);
            }

            $unitPriceMinor = isset($line['unit_price_minor']) ? (int) $line['unit_price_minor'] : null;
            $unitMoney = null;

            if ($unitPriceMinor !== null) {
                $unitMoney = Money::fromMinor($unitPriceMinor, $currency);
            } else {
                if (! array_key_exists('unit_price', $line)) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.unit_price" => ['Unit price is required.'],
                    ]);
                }

                $unitValue = (float) $line['unit_price'];
                $unitMoney = Money::fromDecimal($unitValue, $currency, $minorUnit, $roundRule);
                $unitPriceMinor = $unitMoney->amountMinor();
            }

            $taxCodeIds = array_values(array_filter(
                array_map('intval', $line['tax_code_ids'] ?? []),
                static fn (int $value) => $value > 0
            ));
            $taxCodeIds = array_values(array_unique($taxCodeIds));

            $taxCodes = $taxCodeIds === []
                ? collect()
                : $this->taxService->resolveCodes($companyId, $taxCodeIds);

            if ($taxCodes->count() !== count($taxCodeIds)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.tax_code_ids" => ['One or more tax codes are invalid or inactive.'],
                ]);
            }

            $result = $this->taxService->computeLineTaxes(
                $unitMoney,
                $quantity,
                $taxRegime,
                $taxCodes,
                $roundRule
            );

            $lineSubtotalMinor = $result['subtotal']->amountMinor();
            $lineTaxMinor = $result['tax_total']->amountMinor();
            $lineGrandMinor = $result['grand_total']->amountMinor();

            $subtotalMinor += $lineSubtotalMinor;
            $taxTotalMinor += $lineTaxMinor;
            $grandTotalMinor += $lineGrandMinor;

            $taxRows = [];

            foreach ($result['taxes'] as $sequence => $taxRow) {
                $taxRows[] = [
                    'tax_code_id' => $taxRow['tax_code']->id,
                    'rate_percent' => $taxRow['rate_percent'],
                    'amount_minor' => $taxRow['amount_minor'],
                    'sequence' => $sequence + 1,
                ];
            }

            $lineResults[] = [
                'index' => $index,
                'key' => $line['key'] ?? $index,
                'quantity' => $quantity,
                'unit_price_minor' => $unitPriceMinor,
                'subtotal_minor' => $lineSubtotalMinor,
                'tax_total_minor' => $lineTaxMinor,
                'grand_total_minor' => $lineGrandMinor,
                'taxes' => $taxRows,
            ];
        }

        return [
            'currency' => $currency,
            'minor_unit' => $minorUnit,
            'round_rule' => $roundRule->value,
            'tax_regime' => $taxRegime->value,
            'lines' => $lineResults,
            'totals' => [
                'subtotal_minor' => $subtotalMinor,
                'tax_total_minor' => $taxTotalMinor,
                'grand_total_minor' => $grandTotalMinor,
            ],
        ];
    }

    private function resolveMinorUnit(string $currency): int
    {
        $record = Currency::query()->where('code', $currency)->first();

        if ($record === null) {
            throw ValidationException::withMessages([
                'currency' => ["Currency {$currency} is not configured."],
            ]);
        }

        return (int) $record->minor_unit;
    }
}
