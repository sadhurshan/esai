<?php

namespace App\Actions\PurchaseOrder;

use App\Enums\RfqItemAwardStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\RFQ;
use App\Models\RfqItemAward;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConvertAwardsToPurchaseOrdersAction
{
    public function __construct(
        private readonly CreatePurchaseOrderFromQuoteItemsAction $createPurchaseOrderFromQuoteItemsAction,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @param Collection<int, RfqItemAward> $awards
     * @return Collection<int, PurchaseOrder>
     *
     * @throws ValidationException
     */
    public function execute(RFQ $rfq, Collection $awards): Collection
    {
        if ($awards->isEmpty()) {
            throw ValidationException::withMessages([
                'award_ids' => ['At least one award must be selected.'],
            ]);
        }

        $this->assertAwardsConvertible($rfq, $awards);

        $awards->loadMissing(['supplier', 'quote', 'quoteItem']);

        return DB::transaction(function () use ($rfq, $awards): Collection {
            $purchaseOrders = collect();

            foreach ($awards->groupBy('supplier_id') as $supplierAwards) {
                $supplier = $supplierAwards->first()?->supplier;

                if ($supplier === null) {
                    throw ValidationException::withMessages([
                        'award_ids' => ['One or more awards are missing supplier metadata.'],
                    ]);
                }

                $quoteItemIds = $supplierAwards
                    ->pluck('quote_item_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if ($quoteItemIds === []) {
                    throw ValidationException::withMessages([
                        'award_ids' => ['Awards must include quote item references.'],
                    ]);
                }

                $result = $this->createPurchaseOrderFromQuoteItemsAction->execute($rfq, $supplier, $quoteItemIds);
                /** @var PurchaseOrder $po */
                $po = $result['po'];
                /** @var array<int, PurchaseOrderLine> $lines */
                $lines = $result['lines'];

                foreach ($supplierAwards as $award) {
                    $before = Arr::only($award->getOriginal(), ['po_id']);
                    $award->po_id = $po->id;
                    $award->save();

                    $this->auditLogger->updated($award, $before, ['po_id' => $award->po_id]);

                    $line = $lines[$award->rfq_item_id] ?? $po->lines()
                        ->where('rfq_item_id', $award->rfq_item_id)
                        ->orderByDesc('id')
                        ->first();

                    if ($line instanceof PurchaseOrderLine) {
                        $line->rfq_item_award_id = $award->id;
                        $line->save();
                    }
                }

                $purchaseOrders->push(
                    $po->loadMissing(['lines.taxes.taxCode', 'lines.rfqItem', 'quote.supplier', 'rfq'])
                );
            }

            return $purchaseOrders->values();
        });
    }

    /**
     * @param Collection<int, RfqItemAward> $awards
     */
    private function assertAwardsConvertible(RFQ $rfq, Collection $awards): void
    {
        $rfqIds = $awards->pluck('rfq_id')->unique()->values();

        if ($rfqIds->count() !== 1 || (int) $rfqIds->first() !== (int) $rfq->id) {
            throw ValidationException::withMessages([
                'award_ids' => ['Awards must belong to the same RFQ.'],
            ]);
        }

        $invalid = $awards->first(function (RfqItemAward $award): bool {
            if ($award->status !== RfqItemAwardStatus::Awarded) {
                return true;
            }

            if ($award->po_id !== null) {
                return true;
            }

            return $award->quote_item_id === null;
        });

        if ($invalid instanceof RfqItemAward) {
            throw ValidationException::withMessages([
                'award_ids' => ['Awards must be active, unconverted selections.'],
            ]);
        }
    }
}
