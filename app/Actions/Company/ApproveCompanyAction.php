<?php

namespace App\Actions\Company;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class ApproveCompanyAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(Company $company): Company
    {
        return DB::transaction(function () use ($company): Company {
            $before = $company->getOriginal();

            $company->status = CompanyStatus::Active;
            $company->rejection_reason = null;

            $changes = $company->getDirty();

            if ($changes === []) {
                return $company;
            }

            $company->save();

            $this->auditLogger->updated($company, $before, $changes);

            return $company;
        });
    }
}
