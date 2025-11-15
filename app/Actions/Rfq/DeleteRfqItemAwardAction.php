<?php

namespace App\Actions\Rfq;

use App\Actions\Rfq\Concerns\ManagesRfqAwardState;
use App\Enums\RfqItemAwardStatus;
use App\Models\QuoteItem;
use App\Models\RfqItemAward;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class DeleteRfqItemAwardAction
{
    use ManagesRfqAwardState;

    public function __construct(
        private readonly DatabaseManager $db,
        protected readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function execute(RfqItemAward $award): void
    {
        if ($award->status !== RfqItemAwardStatus::Awarded) {
            throw ValidationException::withMessages([
                'status' => ['Only active awards can be deleted.'],
            ]);
        }

        if ($award->po_id !== null) {
            throw ValidationException::withMessages([
                'po_id' => ['Award already converted to a purchase order. Please cancel or adjust the PO first.'],
            ]);
        }

        $rfq = $award->rfq;
        if ($rfq === null) {
            throw ValidationException::withMessages([
                'rfq_id' => ['Award is missing RFQ context.'],
            ]);
        }

        $awardId = $award->getKey();
        $rfqItemId = $award->rfq_item_id;

        $this->db->transaction(function () use ($awardId, $rfqItemId): void {
            $lockedAward = RfqItemAward::query()
                ->whereKey($awardId)
                ->lockForUpdate()
                ->firstOrFail();

            $rfq = $lockedAward->rfq()->lockForUpdate()->firstOrFail();

            $before = $lockedAward->toArray();

            $quoteItems = QuoteItem::query()
                ->where('rfq_item_id', $rfqItemId)
                ->get();

            foreach ($quoteItems as $quoteItem) {
                $this->updateQuoteItemStatus($quoteItem, 'pending');
            }

            $lockedAward->forceDelete();
            $this->auditLogger->deleted($lockedAward, $before);

            $this->refreshQuoteStatuses($rfq);
            $this->refreshRfqState($rfq);
        });
    }
}
