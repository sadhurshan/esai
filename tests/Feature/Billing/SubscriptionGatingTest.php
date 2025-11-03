<?php

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
    Plan::factory()->create([
        'code' => 'starter',
        'rfqs_per_month' => 5,
        'users_max' => 5,
        'storage_gb' => 10,
    ]);

    $company = Company::factory()->create([
        'plan_code' => 'starter',
        'rfqs_monthly_used' => 5,
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
        'item_name' => 'Housing',
        'type' => 'manufacture',
        'quantity' => 1,
        'material' => 'Aluminum',
        'method' => 'CNC Milling',
        'client_company' => 'Test Co',
        'status' => 'awaiting',
    ]);

    $response->assertStatus(402)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('errors.code', 'rfqs_per_month')
        ->assertJsonPath('errors.upgrade_url', url('/pricing'));
});
