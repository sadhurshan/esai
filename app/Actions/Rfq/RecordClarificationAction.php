<?php

namespace App\Actions\Rfq;

use App\Models\RfqClarification;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class RecordClarificationAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param array{rfq_id: int, user_id: int, kind: string, message: string, attachment_id?: int|null, rfq_version?: int|null} $data
     */
    public function execute(array $data): RfqClarification
    {
        return DB::transaction(function () use ($data): RfqClarification {
            $clarification = RfqClarification::create([
                'rfq_id' => $data['rfq_id'],
                'user_id' => $data['user_id'],
                'kind' => $data['kind'],
                'message' => $data['message'],
                'attachment_id' => $data['attachment_id'] ?? null,
                'rfq_version' => $data['rfq_version'] ?? 1,
            ]);

            $this->auditLogger->created($clarification);

            return $clarification;
        });
    }
}
