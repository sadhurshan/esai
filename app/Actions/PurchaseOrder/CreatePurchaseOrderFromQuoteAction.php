<?php

namespace App\Actions\PurchaseOrder;

use App\Enums\MoneyRoundRule;
use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quote;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use App\Services\LineTaxSyncService;
use App\Services\TotalsCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePurchaseOrderFromQuoteAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly TotalsCalculator $totalsCalculator,
        private readonly LineTaxSyncService $lineTaxSync
    ) {}

    /**
     * @param array<int, int> $quoteItemIds
     */
    public function execute(Quote $quote, array $quoteItemIds): PurchaseOrder
    {
        return DB::transaction(function () use ($quote, $quoteItemIds): PurchaseOrder {
            $items = $quote->items()
                ->whereIn('id', $quoteItemIds)
                ->with(['rfqItem', 'taxes'])
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => ['At least one quote item is required to create a purchase order.'],
                ]);
            }

            $currency = strtoupper($quote->currency ?? 'USD');

            $lineInputs = [];

            foreach ($items as $item) {
                $lineCurrency = strtoupper($item->currency ?? $currency);

                if ($lineCurrency !== $currency) {
                    throw ValidationException::withMessages([
                        'items' => ['All purchase order lines must share the same currency.'],
                    ]);
                }

                $quantity = (float) ($item->rfqItem?->quantity ?? 0);

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'items' => ["RFQ item {$item->rfq_item_id} is missing a valid quantity."],
                    ]);
                }

                $lineInputs[] = [
                    'key' => $item->id,
                    'quantity' => $quantity,
                    'unit_price_minor' => $item->unit_price_minor ?? $this->decimalToMinor($item->unit_price, $currency),
                    'tax_code_ids' => $item->taxes->pluck('tax_code_id')->map(fn ($id) => (int) $id)->values()->all(),
                ];
            }

            $calculation = $this->totalsCalculator->calculate((int) $quote->company_id, $currency, $lineInputs);
            $minorUnit = (int) $calculation['minor_unit'];
            $roundRule = MoneyRoundRule::from($calculation['round_rule']);

            $po = PurchaseOrder::create([
                'company_id' => $quote->company_id,
                'rfq_id' => $quote->rfq_id,
                'quote_id' => $quote->id,
                'supplier_id' => $quote->supplier_id,
                'po_number' => $this->generatePoNumber(),
                'currency' => $currency,
                'status' => 'draft',
                'revision_no' => 0,
                'subtotal' => Money::fromMinor($calculation['totals']['subtotal_minor'], $currency)->toDecimal($minorUnit),
                'tax_amount' => Money::fromMinor($calculation['totals']['tax_total_minor'], $currency)->toDecimal($minorUnit),
                'total' => Money::fromMinor($calculation['totals']['grand_total_minor'], $currency)->toDecimal($minorUnit),
                'subtotal_minor' => (int) $calculation['totals']['subtotal_minor'],
                'tax_amount_minor' => (int) $calculation['totals']['tax_total_minor'],
                'total_minor' => (int) $calculation['totals']['grand_total_minor'],
            ]);

            $lineResults = collect($calculation['lines'])->keyBy('key');
            $lineNo = 1;

            foreach ($items as $item) {
                $result = $lineResults->get($item->id);

                if ($result === null) {
                    throw ValidationException::withMessages([
                        'items' => ['Unable to calculate totals for one or more lines.'],
                    ]);
                }

                $unitPrice = Money::fromMinor($result['unit_price_minor'], $currency)->toDecimal($minorUnit);

                $poLine = PurchaseOrderLine::create([
                    'purchase_order_id' => $po->id,
                    'rfq_item_id' => $item->rfq_item_id,
                    'line_no' => $lineNo++,
                    'description' => $item->rfqItem?->part_name ?? 'Line '.$lineNo,
                    'quantity' => $item->rfqItem?->quantity ?? 1,
                    'uom' => $item->rfqItem?->uom ?? 'pcs',
                    'unit_price' => $unitPrice,
                    'unit_price_minor' => $result['unit_price_minor'],
                    'currency' => $currency,
                    'delivery_date' => null,
                ]);

                $this->lineTaxSync->sync($poLine, (int) $quote->company_id, $result['taxes']);
            }

            $this->auditLogger->created($po);

            $quoteBefore = $quote->getOriginal();
            $quote->status = 'awarded';
            $quote->save();
            $this->auditLogger->updated($quote, $quoteBefore, $quote->getChanges());

            $rfq = $quote->rfq;
            if ($rfq && $rfq->status !== 'awarded') {
                $before = $rfq->getOriginal();
                $rfq->status = 'awarded';
                $rfq->save();
                $this->auditLogger->updated($rfq, $before, $rfq->getChanges());
            }

            return $po->load(['lines.taxes.taxCode', 'supplier', 'quote.supplier']);
        });
    }

    protected function generatePoNumber(): string
    {
        do {
            $number = 'PO-'.Str::upper(Str::random(8));
        } while (PurchaseOrder::where('po_number', $number)->exists());

        return $number;
    }

    private function decimalToMinor(mixed $value, string $currency): int
    {
        $amount = (float) ($value ?? 0);
        $minorUnit = (int) (Currency::query()->where('code', $currency)->value('minor_unit') ?? 2);

        return Money::fromDecimal($amount, $currency, $minorUnit)->amountMinor();
    }
}
