<?php

namespace App\Actions\Company;

use App\Models\Company;
use App\Models\Plan;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class AssignCompanyPlanAction
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Company $company, Plan $plan, array $attributes = []): Company
    {
        $before = Arr::only($company->getOriginal(), ['plan_id', 'plan_code', 'trial_ends_at', 'notes']);

        $payload = [
            'plan_id' => $plan->id,
            'plan_code' => $plan->code,
        ];

        if (array_key_exists('trial_ends_at', $attributes)) {
            $value = $attributes['trial_ends_at'];
            $payload['trial_ends_at'] = $value ? Carbon::parse($value) : null;
        }

        if (array_key_exists('notes', $attributes)) {
            $payload['notes'] = $attributes['notes'];
        }

        $company->forceFill($payload)->save();

        $updated = $company->fresh(['plan']);

        $this->auditLogger->updated(
            $updated,
            $before,
            Arr::only($updated->attributesToArray(), ['plan_id', 'plan_code', 'trial_ends_at', 'notes'])
        );

        return $updated;
    }
}
