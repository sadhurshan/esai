<?php

namespace App\Actions\PurchaseOrder;

use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use App\Services\LineTaxSyncService;
use App\Services\TotalsCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePurchaseOrderFromQuoteItemsAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly TotalsCalculator $totalsCalculator,
        private readonly LineTaxSyncService $lineTaxSync
    )
    {
    }

    /**
     * @param array<int, int> $quoteItemIds
     * @return array{po: PurchaseOrder, lines: array<int, PurchaseOrderLine>}
     */
    public function execute(RFQ $rfq, Supplier $supplier, array $quoteItemIds): array
    {
        return DB::transaction(function () use ($rfq, $supplier, $quoteItemIds): array {
            $quoteItems = QuoteItem::query()
                ->with(['quote', 'rfqItem', 'taxes'])
                ->whereIn('id', $quoteItemIds)
                ->get();

            $quote = $this->resolveQuote($quoteItems);

            if ($quoteItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => ['At least one quote item is required.'],
                ]);
            }

            $po = $this->ensurePurchaseOrder($rfq, $supplier, $quote);

            $currency = strtoupper($po->currency ?? $quote?->currency ?? 'USD');

            $existingRfqItemIds = $po->lines()
                ->whereIn('rfq_item_id', $quoteItems->pluck('rfq_item_id')->all())
                ->pluck('rfq_item_id')
                ->all();

            $lineNo = (int) ($po->lines()->max('line_no') ?? 0) + 1;

            $createdLines = [];

            foreach ($quoteItems as $quoteItem) {
                if (in_array($quoteItem->rfq_item_id, $existingRfqItemIds, true)) {
                    continue;
                }

                $rfqItem = $quoteItem->rfqItem;

                $lineCurrency = strtoupper($quoteItem->currency ?? $currency);

                if ($lineCurrency !== $currency) {
                    throw ValidationException::withMessages([
                        'items' => ['All purchase order lines must use the same currency.'],
                    ]);
                }

                $line = PurchaseOrderLine::create([
                    'purchase_order_id' => $po->id,
                    'rfq_item_id' => $quoteItem->rfq_item_id,
                    'line_no' => $lineNo++,
                    'description' => $rfqItem?->part_name ?? 'RFQ Item '.$quoteItem->rfq_item_id,
                    'quantity' => $rfqItem?->quantity ?? 1,
                    'uom' => $rfqItem?->uom ?? 'pcs',
                    'unit_price' => $quoteItem->unit_price,
                    'unit_price_minor' => $quoteItem->unit_price_minor ?? $this->decimalToMinor($quoteItem->unit_price, $currency),
                    'currency' => $currency,
                    'delivery_date' => now()->addDays((int) $quoteItem->lead_time_days)->toDateString(),
                ]);

                $createdLines[$quoteItem->rfq_item_id] = $line;

                $taxRows = $quoteItem->taxes
                    ->map(fn ($tax): array => [
                        'tax_code_id' => (int) $tax->tax_code_id,
                        'rate_percent' => (float) $tax->rate_percent,
                        'amount_minor' => (int) $tax->amount_minor,
                        'sequence' => $tax->sequence,
                    ])->values()->all();

                $this->lineTaxSync->sync($line, (int) $po->company_id, $taxRows);

                $this->auditLogger->created($line, [
                    'rfq_item_id' => $quoteItem->rfq_item_id,
                    'purchase_order_id' => $po->id,
                ]);
            }

            $this->recalculateTotals($po);

            $po->refresh()->loadMissing(['lines.taxes.taxCode', 'lines.rfqItem', 'quote.supplier', 'rfq', 'supplier']);

            return [
                'po' => $po,
                'lines' => $createdLines,
            ];
        });
    }

    private function ensurePurchaseOrder(RFQ $rfq, Supplier $supplier, ?Quote $quote): PurchaseOrder
    {
        $query = PurchaseOrder::query()
            ->where('rfq_id', $rfq->id)
            ->where('quote_id', $quote?->id);

        $existing = $query->first();

        if ($existing !== null) {
            if ($existing->supplier_id === null && $supplier->id !== null) {
                $before = $existing->getOriginal();
                $existing->fill(['supplier_id' => $supplier->id]);

                if ($existing->isDirty('supplier_id')) {
                    $changes = ['supplier_id' => $existing->supplier_id];
                    $existing->save();

                    $this->auditLogger->updated($existing, $before, $changes);
                }
            }

            return $existing;
        }

        $po = PurchaseOrder::create([
            'company_id' => $rfq->company_id,
            'rfq_id' => $rfq->id,
            'quote_id' => $quote?->id,
            'supplier_id' => $supplier->id,
            'po_number' => $this->generatePoNumber(),
            'currency' => $quote?->currency ?? $rfq->currency ?? 'USD',
            'incoterm' => $rfq->incoterm,
            'tax_percent' => null,
            'status' => 'draft',
            'revision_no' => 0,
        ]);

        $this->auditLogger->created($po, [
            'rfq_id' => $rfq->id,
            'quote_id' => $quote?->id,
            'supplier_id' => $supplier->id,
        ]);

        return $po;
    }

    private function resolveQuote(Collection $quoteItems): ?Quote
    {
        /** @var Quote|null $quote */
        $quote = $quoteItems
            ->map(fn (QuoteItem $item) => $item->quote)
            ->filter()
            ->first();

        return $quote;
    }

    private function generatePoNumber(): string
    {
        do {
            $number = 'PO-'.Str::upper(Str::random(8));
        } while (PurchaseOrder::where('po_number', $number)->exists());

        return $number;
    }

    private function recalculateTotals(PurchaseOrder $po): void
    {
        $po->loadMissing('lines.taxes');

        if ($po->lines->isEmpty()) {
            $po->update([
                'subtotal' => '0.00',
                'tax_amount' => '0.00',
                'total' => '0.00',
                'subtotal_minor' => 0,
                'tax_amount_minor' => 0,
                'total_minor' => 0,
            ]);

            return;
        }

        $currency = strtoupper($po->currency ?? 'USD');

        $lineInputs = $po->lines->map(function (PurchaseOrderLine $line) use ($currency): array {
            return [
                'key' => $line->id,
                'quantity' => (float) $line->quantity,
                'unit_price_minor' => $line->unit_price_minor ?? $this->decimalToMinor($line->unit_price, $currency),
                'tax_code_ids' => $line->taxes->pluck('tax_code_id')->map(fn ($id) => (int) $id)->values()->all(),
            ];
        })->values()->all();

        $calculation = $this->totalsCalculator->calculate((int) $po->company_id, $currency, $lineInputs);
        $minorUnit = (int) $calculation['minor_unit'];
        $lineResults = collect($calculation['lines'])->keyBy('key');

        foreach ($po->lines as $line) {
            $result = $lineResults->get($line->id);

            if ($result === null) {
                continue;
            }

            $unitPrice = Money::fromMinor($result['unit_price_minor'], $currency)->toDecimal($minorUnit);

            $line->unit_price = $unitPrice;
            $line->unit_price_minor = $result['unit_price_minor'];
            $line->currency = $currency;
            $line->save();

            $this->lineTaxSync->sync($line, (int) $po->company_id, $result['taxes']);
        }

        $po->subtotal = Money::fromMinor($calculation['totals']['subtotal_minor'], $currency)->toDecimal($minorUnit);
        $po->tax_amount = Money::fromMinor($calculation['totals']['tax_total_minor'], $currency)->toDecimal($minorUnit);
        $po->total = Money::fromMinor($calculation['totals']['grand_total_minor'], $currency)->toDecimal($minorUnit);
        $po->subtotal_minor = (int) $calculation['totals']['subtotal_minor'];
        $po->tax_amount_minor = (int) $calculation['totals']['tax_total_minor'];
        $po->total_minor = (int) $calculation['totals']['grand_total_minor'];
        $po->save();
    }

    private function decimalToMinor(mixed $value, string $currency): int
    {
        $amount = (float) ($value ?? 0);
        $minorUnit = (int) (Currency::query()->where('code', $currency)->value('minor_unit') ?? 2);

        return Money::fromDecimal($amount, $currency, $minorUnit)->amountMinor();
    }
}
