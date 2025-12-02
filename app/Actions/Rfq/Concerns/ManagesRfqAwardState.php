<?php

namespace App\Actions\Rfq\Concerns;

use App\Enums\RfqItemAwardStatus;
use App\Models\RFQ;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RfqItemAward;
use App\Services\RfqVersionService;
use App\Support\CompanyContext;
use Illuminate\Support\Arr;

/**
 * @property-read \App\Support\Audit\AuditLogger $auditLogger
 * @property-read RfqVersionService $rfqVersionService
 */
trait ManagesRfqAwardState
{
    protected function updateQuoteItemStatus(QuoteItem $quoteItem, string $status): void
    {
        if ($quoteItem->status === $status) {
            return;
        }

        $before = Arr::only($quoteItem->getAttributes(), ['status']);
        $quoteItem->status = $status;
        $quoteItem->save();

        $this->auditLogger->updated($quoteItem, $before, ['status' => $status]);
    }

    protected function refreshQuoteStatuses(RFQ $rfq): void
    {
        $quotes = CompanyContext::bypass(fn () => Quote::query()
            ->with('items')
            ->where('rfq_id', $rfq->id)
            ->get());

        foreach ($quotes as $quote) {
            if ($quote->status === 'withdrawn') {
                continue;
            }

            $awardedCount = $quote->items->where('status', 'awarded')->count();
            $pendingCount = $quote->items->where('status', 'pending')->count();

            $targetStatus = $quote->status;

            if ($awardedCount > 0) {
                $targetStatus = 'awarded';
            } elseif ($pendingCount === 0) {
                $targetStatus = 'rejected';
            } else {
                $targetStatus = 'submitted';
            }

            if ($targetStatus === $quote->status) {
                continue;
            }

            $before = Arr::only($quote->getAttributes(), ['status']);
            $quote->status = $targetStatus;
            $quote->save();

            $this->auditLogger->updated($quote, $before, ['status' => $targetStatus]);
        }
    }

    protected function refreshRfqState(RFQ $rfq): void
    {
        $before = Arr::only($rfq->getAttributes(), ['status', 'is_partially_awarded']);

        $totalItems = (int) $rfq->items()->count();
        $awardedItems = (int) RfqItemAward::query()
            ->where('rfq_id', $rfq->id)
            ->where('status', RfqItemAwardStatus::Awarded)
            ->count();

        if ($totalItems > 0 && $awardedItems >= $totalItems) {
            $rfq->status = 'awarded';
            $rfq->is_partially_awarded = false;
        } elseif ($awardedItems > 0) {
            $rfq->is_partially_awarded = true;
            if ($rfq->status === 'awarded') {
                $rfq->status = 'open';
            }
        } else {
            $rfq->is_partially_awarded = false;
            if ($rfq->status === 'awarded') {
                $rfq->status = 'open';
            }
        }

        $this->rfqVersionService->bump($rfq, null, 'rfq_award_state_refreshed', [
            'from_status' => $before['status'] ?? null,
            'to_status' => $rfq->status,
            'from_is_partially_awarded' => $before['is_partially_awarded'] ?? null,
            'to_is_partially_awarded' => $rfq->is_partially_awarded,
            'total_items' => $totalItems,
            'awarded_items' => $awardedItems,
        ]);
    }
}
