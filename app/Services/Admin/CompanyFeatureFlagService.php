<?php

namespace App\Services\Admin;

use App\Models\Company;
use App\Models\CompanyFeatureFlag;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Arr;

class CompanyFeatureFlagService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Company $company, array $attributes): CompanyFeatureFlag
    {
        $payload = [
            'key' => $attributes['key'],
            'value' => $attributes['value'] ?? null,
        ];

        $flag = $company->featureFlags()->create($payload);

        $this->auditLogger->created($flag);

        return $flag->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CompanyFeatureFlag $flag, array $attributes): CompanyFeatureFlag
    {
        $before = Arr::only($flag->getOriginal(), ['key', 'value']);

        $payload = Arr::only($attributes, ['key', 'value']);

        $flag->fill($payload)->save();

        $flag->refresh();

        $this->auditLogger->updated($flag, $before, Arr::only($flag->attributesToArray(), ['key', 'value']));

        return $flag;
    }

    public function delete(CompanyFeatureFlag $flag): void
    {
        $before = Arr::only($flag->attributesToArray(), ['key', 'value']);

        $flag->delete();

        $this->auditLogger->deleted($flag, $before);
    }
}
