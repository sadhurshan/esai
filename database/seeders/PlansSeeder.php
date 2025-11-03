<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $plans = [
            [
                'code' => 'starter',
                'name' => 'Starter',
                'price_usd' => 2400.00,
                'rfqs_per_month' => 10,
                'users_max' => 5,
                'storage_gb' => 3,
                'erp_integrations_max' => 0,
            ],
            [
                'code' => 'growth',
                'name' => 'Growth',
                'price_usd' => 4800.00,
                'rfqs_per_month' => 100,
                'users_max' => 25,
                'storage_gb' => 25,
                'erp_integrations_max' => 1,
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'price_usd' => null,
                'rfqs_per_month' => 0,
                'users_max' => 0,
                'storage_gb' => 0,
                'erp_integrations_max' => null,
            ],
        ];

        $payload = array_map(function (array $plan) use ($now) {
            return array_merge($plan, [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $plans);

        DB::table('plans')->upsert(
            $payload,
            ['code'],
            ['name', 'price_usd', 'rfqs_per_month', 'users_max', 'storage_gb', 'erp_integrations_max', 'updated_at']
        );
    }
}
