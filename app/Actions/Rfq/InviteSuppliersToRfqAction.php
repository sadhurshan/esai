<?php

namespace App\Actions\Rfq;

use App\Enums\CompanySupplierStatus;
use App\Models\RFQ;
use App\Models\RfqInvitation;
use App\Models\Supplier;
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

            if ($supplierIds === []) {
                return;
            }

            $approvedSupplierIds = Supplier::query()
                ->select('suppliers.id')
                ->join('companies', 'companies.id', '=', 'suppliers.company_id')
                ->whereIn('suppliers.id', $supplierIds)
                ->where('suppliers.status', 'approved')
                ->where('companies.supplier_status', CompanySupplierStatus::Approved)
                ->pluck('suppliers.id')
                ->all();

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
