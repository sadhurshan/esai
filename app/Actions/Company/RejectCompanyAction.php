<?php

namespace App\Actions\Company;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class RejectCompanyAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(Company $company, string $reason): Company
    {
        return DB::transaction(function () use ($company, $reason): Company {
            $before = $company->getOriginal();

            $company->status = CompanyStatus::Rejected;
            $company->rejection_reason = $reason;

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
