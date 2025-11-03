<?php

namespace App\Actions\Rfq;

use App\Models\RFQ;
use App\Models\RfqInvitation;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class InviteSuppliersToRfqAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param array<int, int> $supplierIds
     */
    public function execute(RFQ $rfq, int $invitedByUserId, array $supplierIds): void
    {
        DB::transaction(function () use ($rfq, $invitedByUserId, $supplierIds): void {
            $supplierIds = array_values(array_unique($supplierIds));

            foreach ($supplierIds as $supplierId) {
                $invitation = RfqInvitation::firstOrCreate(
                    [
                        'rfq_id' => $rfq->id,
                        'supplier_id' => $supplierId,
                    ],
                    [
                        'invited_by' => $invitedByUserId,
                        'status' => 'invited',
                    ]
                );

                if ($invitation->wasRecentlyCreated) {
                    $this->auditLogger->created($invitation);
                }
            }
        });
    }
}
