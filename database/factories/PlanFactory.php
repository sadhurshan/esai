<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'code' => 'starter',
            'name' => 'Starter',
            'price_usd' => 2400.00,
            'rfqs_per_month' => 10,
            'invoices_per_month' => 20,
            'users_max' => 5,
            'storage_gb' => 3,
            'erp_integrations_max' => 0,
            'analytics_enabled' => true,
            'analytics_history_months' => 12,
        ];
    }
}
