<?php

namespace App\Actions\PurchaseOrder;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quote;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePurchaseOrderFromQuoteAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param array<int, int> $quoteItemIds
     */
    public function execute(Quote $quote, array $quoteItemIds): PurchaseOrder
    {
        return DB::transaction(function () use ($quote, $quoteItemIds): PurchaseOrder {
            $po = PurchaseOrder::create([
                'company_id' => $quote->company_id,
                'rfq_id' => $quote->rfq_id,
                'quote_id' => $quote->id,
                'po_number' => $this->generatePoNumber(),
                'currency' => $quote->currency,
                'status' => 'draft',
                'revision_no' => 0,
            ]);

            $lineNo = 1;
            $items = $quote->items()->whereIn('id', $quoteItemIds)->with('rfqItem')->get();

            foreach ($items as $item) {
                PurchaseOrderLine::create([
                    'purchase_order_id' => $po->id,
                    'rfq_item_id' => $item->rfq_item_id,
                    'line_no' => $lineNo++,
                    'description' => $item->rfqItem?->part_name ?? 'Line '.$lineNo,
                    'quantity' => $item->rfqItem?->quantity ?? 1,
                    'uom' => $item->rfqItem?->uom ?? 'pcs',
                    'unit_price' => $item->unit_price,
                    'delivery_date' => null,
                ]);
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

            return $po->load('lines');
        });
    }

    protected function generatePoNumber(): string
    {
        do {
            $number = 'PO-'.Str::upper(Str::random(8));
        } while (PurchaseOrder::where('po_number', $number)->exists());

        return $number;
    }
}
