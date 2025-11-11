<?php

namespace App\Http\Requests\Admin;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Plan::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:191', 'alpha_dash', Rule::unique('plans', 'code')],
            'name' => ['required', 'string', 'max:191'],
            'price_usd' => ['required', 'numeric', 'min:0'],
            'rfqs_per_month' => ['required', 'integer', 'min:0'],
            'invoices_per_month' => ['nullable', 'integer', 'min:0'],
            'users_max' => ['nullable', 'integer', 'min:0'],
            'storage_gb' => ['nullable', 'integer', 'min:0'],
            'erp_integrations_max' => ['nullable', 'integer', 'min:0'],
            'analytics_enabled' => ['sometimes', 'boolean'],
            'analytics_history_months' => ['nullable', 'integer', 'min:0'],
            'risk_scores_enabled' => ['sometimes', 'boolean'],
            'risk_history_months' => ['nullable', 'integer', 'min:0'],
            'approvals_enabled' => ['sometimes', 'boolean'],
            'approval_levels_limit' => ['nullable', 'integer', 'min:0'],
            'rma_enabled' => ['sometimes', 'boolean'],
            'rma_monthly_limit' => ['nullable', 'integer', 'min:0'],
            'credit_notes_enabled' => ['sometimes', 'boolean'],
            'global_search_enabled' => ['sometimes', 'boolean'],
            'quote_revisions_enabled' => ['sometimes', 'boolean'],
            'digital_twin_enabled' => ['sometimes', 'boolean'],
            'maintenance_enabled' => ['sometimes', 'boolean'],
            'inventory_enabled' => ['sometimes', 'boolean'],
            'inventory_history_months' => ['nullable', 'integer', 'min:0'],
            'pr_enabled' => ['sometimes', 'boolean'],
            'multi_currency_enabled' => ['sometimes', 'boolean'],
            'tax_engine_enabled' => ['sometimes', 'boolean'],
            'localization_enabled' => ['sometimes', 'boolean'],
            'exports_enabled' => ['sometimes', 'boolean'],
            'export_row_limit' => ['nullable', 'integer', 'min:0'],
            'data_export_enabled' => ['sometimes', 'boolean'],
            'export_history_days' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
