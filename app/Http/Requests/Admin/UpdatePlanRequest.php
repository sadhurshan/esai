<?php

namespace App\Http\Requests\Admin;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $plan instanceof Plan
            ? ($this->user()?->can('update', $plan) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $plan = $this->route('plan');

        return [
            'code' => ['sometimes', 'string', 'max:191', 'alpha_dash', Rule::unique('plans', 'code')->ignore($plan)],
            'name' => ['sometimes', 'string', 'max:191'],
            'price_usd' => ['sometimes', 'numeric', 'min:0'],
            'rfqs_per_month' => ['sometimes', 'integer', 'min:0'],
            'invoices_per_month' => ['sometimes', 'integer', 'min:0'],
            'users_max' => ['sometimes', 'integer', 'min:0'],
            'storage_gb' => ['sometimes', 'integer', 'min:0'],
            'erp_integrations_max' => ['sometimes', 'integer', 'min:0'],
            'analytics_enabled' => ['sometimes', 'boolean'],
            'analytics_history_months' => ['sometimes', 'integer', 'min:0'],
            'risk_scores_enabled' => ['sometimes', 'boolean'],
            'risk_history_months' => ['sometimes', 'integer', 'min:0'],
            'approvals_enabled' => ['sometimes', 'boolean'],
            'approval_levels_limit' => ['sometimes', 'integer', 'min:0'],
            'rma_enabled' => ['sometimes', 'boolean'],
            'rma_monthly_limit' => ['sometimes', 'integer', 'min:0'],
            'credit_notes_enabled' => ['sometimes', 'boolean'],
            'global_search_enabled' => ['sometimes', 'boolean'],
            'quote_revisions_enabled' => ['sometimes', 'boolean'],
            'digital_twin_enabled' => ['sometimes', 'boolean'],
            'maintenance_enabled' => ['sometimes', 'boolean'],
            'inventory_enabled' => ['sometimes', 'boolean'],
            'inventory_history_months' => ['sometimes', 'integer', 'min:0'],
            'pr_enabled' => ['sometimes', 'boolean'],
            'multi_currency_enabled' => ['sometimes', 'boolean'],
            'tax_engine_enabled' => ['sometimes', 'boolean'],
            'localization_enabled' => ['sometimes', 'boolean'],
            'exports_enabled' => ['sometimes', 'boolean'],
            'export_row_limit' => ['sometimes', 'integer', 'min:0'],
            'data_export_enabled' => ['sometimes', 'boolean'],
            'export_history_days' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
