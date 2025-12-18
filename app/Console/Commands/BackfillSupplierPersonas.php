<?php

namespace App\Console\Commands;

use App\Models\RfqInvitation;
use App\Models\Supplier;
use App\Services\SupplierPersonaService;
use App\Support\CompanyContext;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class BackfillSupplierPersonas extends Command
{
    protected $signature = 'personas:backfill-suppliers {--supplier_id=} {--company_id=}';

    protected $description = 'Mark owner accounts as supplier-capable and rebuild supplier contact mappings.';

    public function __construct(private readonly SupplierPersonaService $personaService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $supplierFilter = $this->option('supplier_id');
        $companyFilter = $this->option('company_id');

        $this->backfillOwners($supplierFilter !== null ? (int) $supplierFilter : null);
        $this->backfillInvitations(
            $supplierFilter !== null ? (int) $supplierFilter : null,
            $companyFilter !== null ? (int) $companyFilter : null
        );

        $this->info('Supplier persona backfill completed.');

        return self::SUCCESS;
    }

    private function backfillOwners(?int $supplierId = null): void
    {
        $query = Supplier::query()->with(['company.owner']);

        if ($supplierId !== null) {
            $query->whereKey($supplierId);
        }

        CompanyContext::bypass(function () use ($query): void {
            $query->chunkById(100, function (Collection $suppliers): void {
                foreach ($suppliers as $supplier) {
                    $this->personaService->ensureOwnerPersona($supplier);
                }
            });
        });
    }

    private function backfillInvitations(?int $supplierId = null, ?int $companyId = null): void
    {
        $query = RfqInvitation::query()
            ->with(['supplier.company.owner', 'rfq:id,company_id']);

        if ($supplierId !== null) {
            $query->where('supplier_id', $supplierId);
        }

        if ($companyId !== null) {
            $query->whereHas('rfq', static function ($rfqQuery) use ($companyId): void {
                $rfqQuery->where('company_id', $companyId);
            });
        }

        CompanyContext::bypass(function () use ($query): void {
            $query->chunkById(100, function (Collection $invitations): void {
                foreach ($invitations as $invitation) {
                    $rfqCompanyId = $invitation->rfq?->company_id;

                    if ($rfqCompanyId === null) {
                        continue;
                    }

                    $this->personaService->ensureBuyerContact($invitation->supplier, (int) $rfqCompanyId, null, false);
                }
            });
        });
    }
}
