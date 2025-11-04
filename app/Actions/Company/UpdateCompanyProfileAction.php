<?php

namespace App\Actions\Company;

use App\Models\Company;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class UpdateCompanyProfileAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(Company $company, array $payload): Company
    {
        return DB::transaction(function () use ($company, $payload): Company {
            $before = $company->getOriginal();

            $company->fill($payload);

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
