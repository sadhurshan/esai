<?php

namespace App\Services\Admin;

use App\Models\Plan;

class PlanService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Plan
    {
        return Plan::create($this->normalize($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Plan $plan, array $attributes): Plan
    {
        $plan->fill($this->normalize($attributes));
        $plan->save();

        return $plan->fresh();
    }

    public function delete(Plan $plan): void
    {
        $plan->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalize(array $attributes): array
    {
        $booleanFields = [
            'analytics_enabled',
            'risk_scores_enabled',
            'approvals_enabled',
            'rma_enabled',
            'credit_notes_enabled',
            'global_search_enabled',
            'quote_revisions_enabled',
            'digital_twin_enabled',
            'maintenance_enabled',
            'inventory_enabled',
            'pr_enabled',
            'multi_currency_enabled',
            'tax_engine_enabled',
            'localization_enabled',
            'exports_enabled',
            'data_export_enabled',
        ];

        $integerFields = [
            'rfqs_per_month',
            'invoices_per_month',
            'users_max',
            'storage_gb',
            'erp_integrations_max',
            'analytics_history_months',
            'risk_history_months',
            'approval_levels_limit',
            'rma_monthly_limit',
            'inventory_history_months',
            'export_row_limit',
            'export_history_days',
        ];

        foreach ($booleanFields as $field) {
            if (array_key_exists($field, $attributes)) {
                $attributes[$field] = (bool) $attributes[$field];
            }
        }

        foreach ($integerFields as $field) {
            if (array_key_exists($field, $attributes) && $attributes[$field] !== null) {
                $attributes[$field] = (int) $attributes[$field];
            }
        }

        if (array_key_exists('code', $attributes) && is_string($attributes['code'])) {
            $attributes['code'] = strtolower($attributes['code']);
        }

        return $attributes;
    }
}
