<?php

namespace App\Services;

use App\Models\RFQ;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Arr;

class RfqVersionService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function bump(RFQ $rfq, ?int $revisionId = null, ?string $reason = null, array $context = []): void
    {
        $before = Arr::only($rfq->getAttributes(), ['rfq_version', 'current_revision_id']);

        $rfq->incrementVersion($revisionId);

        $payload = Arr::only($rfq->getAttributes(), ['rfq_version', 'current_revision_id']);

        if ($reason !== null || $context !== []) {
            $payload['meta'] = array_filter([
                'reason' => $reason,
                'context' => $context ?: null,
            ], static fn ($value) => $value !== null);
        }

        $this->auditLogger->updated($rfq, $before, $payload);
    }
}
