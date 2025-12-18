<?php

namespace App\Actions\PurchaseOrder;

use App\Actions\Rfq\Concerns\ManagesRfqAwardState;
use App\Enums\RfqItemAwardStatus;
use App\Models\PurchaseOrder;
use App\Models\QuoteItem;
use App\Models\RfqItemAward;
use App\Services\RfqVersionService;
use App\Support\Audit\AuditLogger;
use App\Support\CompanyContext;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CancelPurchaseOrderAction
{
    use ManagesRfqAwardState;

    public function __construct(
        private readonly DatabaseManager $db,
        protected readonly AuditLogger $auditLogger,
        protected readonly RfqVersionService $rfqVersionService,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function execute(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $this->db->transaction(function () use ($purchaseOrder): PurchaseOrder {
            $po = PurchaseOrder::query()
                ->whereKey($purchaseOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($po->status, ['draft', 'sent'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only draft or sent purchase orders can be cancelled.'],
                ]);
            }

            $rfq = $po->rfq()->lockForUpdate()->first();

            $before = $po->only(['status', 'cancelled_at']);
            $po->status = 'cancelled';
            $po->cancelled_at = now();
            $po->save();

            $this->auditLogger->updated($po, $before, [
                'status' => $po->status,
                'cancelled_at' => $po->cancelled_at,
            ]);

            $awards = RfqItemAward::query()
                ->where('po_id', $po->getKey())
                ->where('status', RfqItemAwardStatus::Awarded)
                ->lockForUpdate()
                ->get();

            if ($awards->isEmpty()) {
                return $po;
            }

            /** @var Collection<int, int> $rfqItemIds */
            $rfqItemIds = $awards->pluck('rfq_item_id')->unique()->values();

            /** @var Collection<int, Collection<int, QuoteItem>> $quoteItemsByRfq */
            $quoteItemsByRfq = CompanyContext::bypass(fn () => QuoteItem::query()
                ->whereIn('rfq_item_id', $rfqItemIds)
                ->get()
                ->groupBy('rfq_item_id'));

            foreach ($awards as $award) {
                $beforeAward = $award->only(['status']);
                $award->status = RfqItemAwardStatus::Cancelled;
                $award->save();

                $this->auditLogger->updated($award, $beforeAward, [
                    'status' => $award->status->value,
                ]);
            }

            foreach ($rfqItemIds as $rfqItemId) {
                foreach ($quoteItemsByRfq->get($rfqItemId, collect()) as $quoteItem) {
                    $this->updateQuoteItemStatus($quoteItem, 'pending');
                }
            }

            if ($rfq) {
                $this->refreshQuoteStatuses($rfq);
                $this->refreshRfqState($rfq);
            }

            return $po;
        });
    }
}
