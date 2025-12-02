<?php

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('blocks rfq creation when monthly limit exceeded', function (): void {
    $plan = Plan::factory()->create([
        'code' => 'starter',
        'rfqs_per_month' => 5,
        'users_max' => 5,
        'storage_gb' => 10,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'rfqs_monthly_used' => 5,
        'status' => CompanyStatus::Active,
    ]);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'stripe_status' => 'active',
    ]);

    $user = User::factory()->for($company)->create();

    actingAs($user);

    $response = postJson('/api/rfqs', [
        'title' => 'Housing',
        'method' => 'cnc',
        'material' => 'aluminum',
        'delivery_location' => 'Test Co',
        'items' => [
            [
                'part_number' => 'Housing line',
                'description' => 'Housing line',
                'qty' => 1,
                'uom' => 'pcs',
                'method' => 'cnc',
                'material' => 'aluminum',
                'tolerance' => null,
                'finish' => null,
            ],
        ],
    ]);

    $response->assertStatus(402)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('errors.code', 'rfqs_per_month')
        ->assertJsonPath('errors.upgrade_url', url('/app/setup/plan').'?mode=change');
});
