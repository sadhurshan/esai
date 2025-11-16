<?php

namespace App\Services\Admin;

use App\Enums\CompanyStatus;
use App\Actions\Company\AssignCompanyPlanAction;
use App\Models\Company;
use App\Models\Plan;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Arr;

class CompanyService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AssignCompanyPlanAction $assignCompanyPlanAction,
    )
    {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function assignPlan(Company $company, Plan $plan, array $attributes = []): Company
    {
        return $this->assignCompanyPlanAction->execute($company, $plan, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateStatus(Company $company, CompanyStatus $status, array $attributes = []): Company
    {
        $before = Arr::only($company->getOriginal(), ['status', 'notes']);

        $payload = ['status' => $status->value];

        if (array_key_exists('notes', $attributes)) {
            $payload['notes'] = $attributes['notes'];
        }

        $company->forceFill($payload)->save();

        $updated = $company->fresh(['plan']);

    $this->auditLogger->updated($updated, $before, Arr::only($updated->attributesToArray(), ['status', 'notes']));

        return $updated;
    }
}
