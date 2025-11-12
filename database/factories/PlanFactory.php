<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'code' => 'plan-'.Str::lower(Str::random(8)),
            'name' => $this->faker->company().' Plan',
            'price_usd' => 2400.00,
            'rfqs_per_month' => 10,
            'invoices_per_month' => 20,
            'users_max' => 5,
            'storage_gb' => 3,
            'erp_integrations_max' => 0,
            'analytics_enabled' => true,
            'analytics_history_months' => 12,
            'risk_scores_enabled' => true,
            'risk_history_months' => 12,
            'approvals_enabled' => true,
            'approval_levels_limit' => 5,
            'rma_enabled' => true,
            'rma_monthly_limit' => 20,
            'credit_notes_enabled' => true,
            'global_search_enabled' => true,
            'quote_revisions_enabled' => true,
            'digital_twin_enabled' => false,
            'maintenance_enabled' => false,
            'inventory_enabled' => true,
            'inventory_history_months' => 12,
            'pr_enabled' => false,
            'multi_currency_enabled' => true,
            'tax_engine_enabled' => true,
            'localization_enabled' => true,
            'exports_enabled' => true,
            'export_row_limit' => 50000,
            'data_export_enabled' => true,
            'export_history_days' => 30,
        ];
    }
}
