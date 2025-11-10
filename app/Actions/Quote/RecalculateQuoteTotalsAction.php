<?php

namespace App\Actions\Quote;

use App\Models\Currency;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Services\LineTaxSyncService;
use App\Services\TotalsCalculator;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class RecalculateQuoteTotalsAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly TotalsCalculator $totalsCalculator,
        private readonly LineTaxSyncService $lineTaxSync,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Quote $quote): Quote
    {
        $quote->loadMissing(['items.taxes', 'items.rfqItem']);

        if ($quote->company_id === null) {
            throw ValidationException::withMessages([
                'quote_id' => ['Quote company context is missing.'],
            ]);
        }

        $companyId = (int) $quote->company_id;
        $currency = strtoupper($quote->currency ?? 'USD');

        $lineInputs = $quote->items
            ->map(function (QuoteItem $item) use ($currency): array {
                $lineCurrency = strtoupper($item->currency ?? $currency);

                if ($lineCurrency !== $currency) {
                    throw ValidationException::withMessages([
                        'items' => ['All quote items must use the same currency as the quote.'],
                    ]);
                }

                $quantity = (float) ($item->rfqItem?->quantity ?? 0);

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'items' => ["Quote item {$item->id} is missing a valid quantity."],
                    ]);
                }

                return [
                    'key' => $item->id,
                    'quantity' => $quantity,
                    'unit_price_minor' => $item->unit_price_minor ?? $this->decimalToMinor($item->unit_price, $currency),
                    'tax_code_ids' => $item->taxes
                        ->pluck('tax_code_id')
                        ->map(static fn ($value): int => (int) $value)
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $before = $quote->only([
            'subtotal',
            'tax_amount',
            'total',
            'subtotal_minor',
            'tax_amount_minor',
            'total_minor',
        ]);

        return $this->db->transaction(function () use ($quote, $companyId, $currency, $lineInputs, $before): Quote {
            $calculation = $this->totalsCalculator->calculate($companyId, $currency, $lineInputs);
            $minorUnit = (int) $calculation['minor_unit'];
            $lineResults = collect($calculation['lines'])->keyBy('key');

            foreach ($quote->items as $item) {
                $result = $lineResults->get($item->id);

                if ($result === null) {
                    continue;
                }

                $item->unit_price = Money::fromMinor($result['unit_price_minor'], $currency)->toDecimal($minorUnit);
                $item->unit_price_minor = (int) $result['unit_price_minor'];
                $item->currency = $currency;
                $item->save();

                $this->lineTaxSync->sync($item, (int) $quote->company_id, $result['taxes']);
                $item->load('taxes.taxCode');
            }

            $quote->subtotal = Money::fromMinor($calculation['totals']['subtotal_minor'], $currency)->toDecimal($minorUnit);
            $quote->tax_amount = Money::fromMinor($calculation['totals']['tax_total_minor'], $currency)->toDecimal($minorUnit);
            $quote->total = Money::fromMinor($calculation['totals']['grand_total_minor'], $currency)->toDecimal($minorUnit);
            $quote->subtotal_minor = (int) $calculation['totals']['subtotal_minor'];
            $quote->tax_amount_minor = (int) $calculation['totals']['tax_total_minor'];
            $quote->total_minor = (int) $calculation['totals']['grand_total_minor'];
            $quote->save();

            $this->auditLogger->updated($quote, $before, [
                'subtotal' => $quote->subtotal,
                'tax_amount' => $quote->tax_amount,
                'total' => $quote->total,
                'subtotal_minor' => $quote->subtotal_minor,
                'tax_amount_minor' => $quote->tax_amount_minor,
                'total_minor' => $quote->total_minor,
            ]);

            return $quote->load(['items.taxes.taxCode', 'items.rfqItem']);
        });
    }

    private function decimalToMinor(mixed $value, string $currency): int
    {
        $amount = (float) ($value ?? 0);
        $minorUnit = $this->minorUnit($currency);

        return Money::fromDecimal($amount, $currency, $minorUnit)->amountMinor();
    }

    private function minorUnit(string $currency): int
    {
        static $cache = [];

        $currency = strtoupper($currency);

        if (! array_key_exists($currency, $cache)) {
            $record = Currency::query()->where('code', $currency)->first();
            $cache[$currency] = $record?->minor_unit ?? 2;
        }

        return (int) $cache[$currency];
    }
}
