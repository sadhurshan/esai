<?php

namespace App\Support;

use App\Exceptions\RfqResponseWindowException;
use App\Models\Quote;
use App\Models\RFQ;

class RfqResponseWindowGuard
{
    public function ensureOpenForResponses(RFQ $rfq, string $actionDescription = 'respond to this RFQ'): void
    {
        if ($rfq->status !== RFQ::STATUS_OPEN) {
            throw new RfqResponseWindowException(
                sprintf('RFQ is closed for responses; unable to %s.', $actionDescription),
                409,
                [
                    'rfq' => [sprintf('RFQ status must be open to %s.', $actionDescription)],
                ]
            );
        }

        $deadline = $rfq->due_at ?? $rfq->close_at;

        if ($deadline === null) {
            return;
        }

        if (now()->greaterThan($deadline)) {
            throw new RfqResponseWindowException(
                sprintf('RFQ is closed for responses; unable to %s.', $actionDescription),
                409,
                [
                    'rfq' => [sprintf('RFQ deadline passed on %s.', $deadline->toDateTimeString())],
                ]
            );
        }
    }

    public function ensureQuoteRfqOpenForResponses(Quote $quote, string $actionDescription = 'update this quote'): void
    {
        $quote->loadMissing('rfq');

        $rfq = $quote->rfq;

        if ($rfq === null) {
            throw new RfqResponseWindowException(
                'Quote cannot be modified because its RFQ is unavailable.',
                422,
                [
                    'rfq' => [sprintf('RFQ context is required to %s.', $actionDescription)],
                ]
            );
        }

        $this->ensureOpenForResponses($rfq, $actionDescription);
    }
}
