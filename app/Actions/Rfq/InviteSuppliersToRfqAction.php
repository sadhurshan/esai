<?php

namespace App\Actions\Rfq;

use App\Enums\CompanySupplierStatus;
use App\Models\RFQ;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use App\Services\RfqVersionService;
use App\Support\Audit\AuditLogger;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;

class InviteSuppliersToRfqAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RfqVersionService $rfqVersionService,
    ) {}

    /**
     * @param array<int, int> $supplierIds
     */
    public function execute(RFQ $rfq, int $invitedByUserId, array $supplierIds): void
    {
        $createdInvitationIds = [];

        DB::transaction(function () use ($rfq, $invitedByUserId, $supplierIds, &$createdInvitationIds): void {
            $supplierIds = array_values(array_unique($supplierIds));

            if ($supplierIds === []) {
                return;
            }

            $approvedSupplierIds = CompanyContext::bypass(static function () use ($supplierIds) {
                return Supplier::query()
                    ->select('suppliers.id')
                    ->join('companies', 'companies.id', '=', 'suppliers.company_id')
                    ->whereIn('suppliers.id', $supplierIds)
                    ->where('suppliers.status', 'approved')
                    ->where('companies.supplier_status', CompanySupplierStatus::Approved)
                    ->pluck('suppliers.id')
                    ->all();
            });

            if ($approvedSupplierIds === []) {
                return;
            }

            foreach ($approvedSupplierIds as $supplierId) {
                $invitation = RfqInvitation::firstOrCreate(
                    [
                        'rfq_id' => $rfq->id,
                        'supplier_id' => $supplierId,
                    ],
                    [
                        'invited_by' => $invitedByUserId,
                        'status' => RfqInvitation::STATUS_PENDING,
                    ]
                );

                if ($invitation->wasRecentlyCreated) {
                    $this->auditLogger->created($invitation);
                    $createdInvitationIds[] = $invitation->id;
                }
            }
        });

        if ($createdInvitationIds !== []) {
            $this->rfqVersionService->bump($rfq, null, 'rfq_invitation_created', [
                'invitation_ids' => $createdInvitationIds,
            ]);
        }
    }
}
