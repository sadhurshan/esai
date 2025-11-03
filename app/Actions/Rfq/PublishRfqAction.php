<?php

namespace App\Actions\Rfq;

use App\Models\RFQ;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;

class PublishRfqAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(RFQ $rfq): RFQ
    {
        return DB::transaction(function () use ($rfq): RFQ {
            $before = $rfq->getOriginal();

            $rfq->status = 'open';
            $rfq->publish_at = $rfq->publish_at ?? now();
            $rfq->due_at = $rfq->due_at ?? now()->addDays(7);
            $rfq->save();

            $this->auditLogger->updated($rfq, $before, $rfq->getChanges());

            return $rfq;
        });
    }
}
