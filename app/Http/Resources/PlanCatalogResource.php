<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Plan */
class PlanCatalogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'price_usd' => $this->price_usd,
            'rfqs_per_month' => $this->rfqs_per_month,
            'invoices_per_month' => $this->invoices_per_month,
            'users_max' => $this->users_max,
            'storage_gb' => $this->storage_gb,
            'erp_integrations_max' => $this->erp_integrations_max,
            'analytics_enabled' => $this->analytics_enabled,
            'analytics_history_months' => $this->analytics_history_months,
            'risk_scores_enabled' => $this->risk_scores_enabled,
            'risk_history_months' => $this->risk_history_months,
            'approvals_enabled' => $this->approvals_enabled,
            'approval_levels_limit' => $this->approval_levels_limit,
            'rma_enabled' => $this->rma_enabled,
            'rma_monthly_limit' => $this->rma_monthly_limit,
            'credit_notes_enabled' => $this->credit_notes_enabled,
            'global_search_enabled' => $this->global_search_enabled,
            'quote_revisions_enabled' => $this->quote_revisions_enabled,
            'digital_twin_enabled' => $this->digital_twin_enabled,
            'maintenance_enabled' => $this->maintenance_enabled,
            'inventory_enabled' => $this->inventory_enabled,
            'inventory_history_months' => $this->inventory_history_months,
            'pr_enabled' => $this->pr_enabled,
            'multi_currency_enabled' => $this->multi_currency_enabled,
            'tax_engine_enabled' => $this->tax_engine_enabled,
            'localization_enabled' => $this->localization_enabled,
            'exports_enabled' => $this->exports_enabled,
            'export_row_limit' => $this->export_row_limit,
            'data_export_enabled' => $this->data_export_enabled,
            'export_history_days' => $this->export_history_days,
            'is_free' => $this->price_usd === null || (float) $this->price_usd <= 0,
        ];
    }
}
