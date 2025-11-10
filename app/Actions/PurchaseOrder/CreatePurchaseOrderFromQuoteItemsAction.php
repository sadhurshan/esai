<?php

namespace App\Actions\PurchaseOrder;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CreatePurchaseOrderFromQuoteItemsAction
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param array<int, int> $quoteItemIds
     * @return array{po: PurchaseOrder, lines: array<int, PurchaseOrderLine>}
     */
    public function execute(RFQ $rfq, Supplier $supplier, array $quoteItemIds): array
    {
        $quoteItems = QuoteItem::query()
            ->with(['quote', 'rfqItem'])
            ->whereIn('id', $quoteItemIds)
            ->get();

    $quote = $this->resolveQuote($quoteItems);

    $po = $this->ensurePurchaseOrder($rfq, $supplier, $quote);

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

            $line = PurchaseOrderLine::create([
                'purchase_order_id' => $po->id,
                'rfq_item_id' => $quoteItem->rfq_item_id,
                'line_no' => $lineNo++,
                'description' => $rfqItem?->part_name ?? 'RFQ Item '.$quoteItem->rfq_item_id,
                'quantity' => $rfqItem?->quantity ?? 1,
                'uom' => $rfqItem?->uom ?? 'pcs',
                'unit_price' => $quoteItem->unit_price,
                'delivery_date' => now()->addDays((int) $quoteItem->lead_time_days)->toDateString(),
            ]);

            $createdLines[$quoteItem->rfq_item_id] = $line;

            $this->auditLogger->created($line, [
                'rfq_item_id' => $quoteItem->rfq_item_id,
                'purchase_order_id' => $po->id,
            ]);
        }

    $po->refresh()->loadMissing(['lines.rfqItem', 'quote.supplier', 'rfq', 'supplier']);

        return [
            'po' => $po,
            'lines' => $createdLines,
        ];
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
}
