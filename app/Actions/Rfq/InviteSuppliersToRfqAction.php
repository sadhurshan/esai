<?php

namespace App\Actions\Rfq;

use App\Enums\CompanySupplierStatus;
use App\Models\RFQ;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use App\Services\RfqVersionService;
use App\Services\SupplierPersonaService;
use App\Support\Audit\AuditLogger;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;

class InviteSuppliersToRfqAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RfqVersionService $rfqVersionService,
        private readonly SupplierPersonaService $supplierPersonaService,
    ) {}

    /**
     * @param array<int, int> $supplierIds
     */
    public function execute(RFQ $rfq, int $invitedByUserId, array $supplierIds): void
    {
        $createdInvitationIds = [];
        $rfq->loadMissing('company');
        $buyerCompany = $rfq->company;

        DB::transaction(function () use ($rfq, $invitedByUserId, $supplierIds, &$createdInvitationIds): void {
            $supplierIds = array_values(array_unique($supplierIds));

            if ($supplierIds === []) {
                return;
            }

            $approvedSuppliers = CompanyContext::bypass(static function () use ($supplierIds) {
                return Supplier::query()
                    ->select('suppliers.*')
                    ->with(['company.owner'])
                    ->join('companies', 'companies.id', '=', 'suppliers.company_id')
                    ->whereIn('suppliers.id', $supplierIds)
                    ->where('suppliers.status', 'approved')
                    ->where('companies.supplier_status', CompanySupplierStatus::Approved)
                    ->get()
                    ->keyBy('id');
            });

            if ($approvedSuppliers->isEmpty()) {
                return;
            }

            foreach ($approvedSuppliers as $supplierId => $supplier) {
                $invitation = RfqInvitation::withTrashed()->firstOrNew([
                    'rfq_id' => $rfq->id,
                    'supplier_id' => $supplierId,
                ]);

                $wasNew = ! $invitation->exists;
                $wasRestored = $invitation->exists && $invitation->trashed();

                $invitation->fill([
                    'company_id' => $rfq->company_id,
                    'invited_by' => $invitedByUserId,
                    'status' => RfqInvitation::STATUS_PENDING,
                ]);

                if ($invitation->trashed()) {
                    $invitation->restore();
                }

                $invitation->save();

                if ($wasNew || $wasRestored) {
                    $this->auditLogger->created($invitation);
                    $createdInvitationIds[] = $invitation->id;
                }

                if ($rfq->company_id !== null) {
                    $this->supplierPersonaService->ensureBuyerContact($supplier, (int) $rfq->company_id, $rfq->company);
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
