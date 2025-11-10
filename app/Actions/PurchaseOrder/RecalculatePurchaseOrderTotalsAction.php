<?php

namespace App\Actions\PurchaseOrder;

use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\LineTaxSyncService;
use App\Services\TotalsCalculator;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class RecalculatePurchaseOrderTotalsAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly TotalsCalculator $totalsCalculator,
        private readonly LineTaxSyncService $lineTaxSync,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $purchaseOrder->loadMissing(['lines.taxes']);

        if ($purchaseOrder->company_id === null) {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['Purchase order company context is missing.'],
            ]);
        }

        $companyId = (int) $purchaseOrder->company_id;
        $currency = strtoupper($purchaseOrder->currency ?? 'USD');

        $lineInputs = $purchaseOrder->lines
            ->map(function (PurchaseOrderLine $line) use ($currency): array {
                $lineCurrency = strtoupper($line->currency ?? $currency);

                if ($lineCurrency !== $currency) {
                    throw ValidationException::withMessages([
                        'lines' => ['All purchase order lines must use the same currency as the purchase order.'],
                    ]);
                }

                $quantity = (float) $line->quantity;

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'lines' => ["Purchase order line {$line->id} must have a quantity greater than zero."],
                    ]);
                }

                return [
                    'key' => $line->id,
                    'quantity' => $quantity,
                    'unit_price_minor' => $line->unit_price_minor ?? $this->decimalToMinor($line->unit_price, $currency),
                    'tax_code_ids' => $line->taxes
                        ->pluck('tax_code_id')
                        ->map(static fn ($value): int => (int) $value)
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $before = $purchaseOrder->only([
            'subtotal',
            'tax_amount',
            'total',
            'subtotal_minor',
            'tax_amount_minor',
            'total_minor',
        ]);

        return $this->db->transaction(function () use ($purchaseOrder, $companyId, $currency, $lineInputs, $before): PurchaseOrder {
            $calculation = $this->totalsCalculator->calculate($companyId, $currency, $lineInputs);
            $minorUnit = (int) $calculation['minor_unit'];
            $lineResults = collect($calculation['lines'])->keyBy('key');

            foreach ($purchaseOrder->lines as $line) {
                $result = $lineResults->get($line->id);

                if ($result === null) {
                    continue;
                }

                $line->unit_price = Money::fromMinor($result['unit_price_minor'], $currency)->toDecimal($minorUnit);
                $line->unit_price_minor = (int) $result['unit_price_minor'];
                $line->currency = $currency;
                $line->save();

                $this->lineTaxSync->sync($line, (int) $purchaseOrder->company_id, $result['taxes']);
                $line->load('taxes.taxCode');
            }

            $purchaseOrder->subtotal = Money::fromMinor($calculation['totals']['subtotal_minor'], $currency)->toDecimal($minorUnit);
            $purchaseOrder->tax_amount = Money::fromMinor($calculation['totals']['tax_total_minor'], $currency)->toDecimal($minorUnit);
            $purchaseOrder->total = Money::fromMinor($calculation['totals']['grand_total_minor'], $currency)->toDecimal($minorUnit);
            $purchaseOrder->subtotal_minor = (int) $calculation['totals']['subtotal_minor'];
            $purchaseOrder->tax_amount_minor = (int) $calculation['totals']['tax_total_minor'];
            $purchaseOrder->total_minor = (int) $calculation['totals']['grand_total_minor'];
            $purchaseOrder->save();

            $this->auditLogger->updated($purchaseOrder, $before, [
                'subtotal' => $purchaseOrder->subtotal,
                'tax_amount' => $purchaseOrder->tax_amount,
                'total' => $purchaseOrder->total,
                'subtotal_minor' => $purchaseOrder->subtotal_minor,
                'tax_amount_minor' => $purchaseOrder->tax_amount_minor,
                'total_minor' => $purchaseOrder->total_minor,
            ]);

            return $purchaseOrder->load(['lines.taxes.taxCode', 'supplier', 'rfq']);
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
