<?php

use App\Models\Asset;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Location;
use App\Models\MaintenanceProcedure;
use App\Models\Plan;
use App\Models\ProcedureStep;
use App\Models\Subscription;
use App\Models\System;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createDigitalTwinUser(bool $maintenanceEnabled = true): User
{
    $plan = Plan::factory()->create([
        'code' => 'pro-dt-'.Str::random(5),
        'name' => 'Pro Digital Twin',
        'digital_twin_enabled' => true,
        'maintenance_enabled' => $maintenanceEnabled,
        'rfqs_per_month' => 0,
        'invoices_per_month' => 0,
        'users_max' => 10,
        'storage_gb' => 50,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'status' => 'active',
        'registration_no' => 'REG-12345',
        'tax_id' => 'TAX-67890',
        'country' => 'US',
        'email_domain' => 'example.com',
        'primary_contact_name' => 'Primary Contact',
        'primary_contact_email' => 'primary@example.com',
        'primary_contact_phone' => '+1-555-0100',
    ]);

    $customer = Customer::factory()->for($company)->create();

    Subscription::factory()->for($company)->create([
        'customer_id' => $customer->id,
        'stripe_status' => 'active',
    ]);

    $user = User::factory()->for($company)->create([
        'role' => 'buyer_admin',
    ]);

    actingAs($user);

    return $user;
}

it('creates a location with the expected response envelope', function () {
    $user = createDigitalTwinUser();

    $payload = [
        'name' => 'Main Plant',
        'code' => 'PLANT-A',
        'notes' => 'Primary site',
    ];

    $response = $this->postJson('/api/digital-twin/locations', $payload);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'Main Plant');

    $this->assertDatabaseHas('locations', [
        'company_id' => $user->company_id,
        'name' => 'Main Plant',
    ]);
});

it('blocks maintenance endpoints when maintenance feature is disabled', function () {
    createDigitalTwinUser(false);

    $response = $this->getJson('/api/digital-twin/procedures');

    $response->assertStatus(402)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('errors.code', 'maintenance_disabled');
});

it('links a maintenance procedure to an asset and records the schedule', function () {
    $user = createDigitalTwinUser();
    $company = $user->company;

    $location = Location::factory()->for($company)->create();
    $system = System::factory()->for($company)->for($location)->create();

    $asset = Asset::factory()->for($company)->for($location)->create([
        'system_id' => $system->id,
    ]);

    $procedure = MaintenanceProcedure::factory()->for($company)->create([
        'code' => 'MP-100',
        'title' => 'Quarterly Safety Check',
        'category' => 'inspection',
    ]);

    ProcedureStep::factory()->for($procedure, 'procedure')->create([
        'step_no' => 1,
        'title' => 'Inspect guards',
        'instruction_md' => 'Verify all guards are in place.',
    ]);

    $payload = [
        'frequency_value' => 90,
        'frequency_unit' => 'day',
        'last_done_at' => now()->subDays(10)->toDateString(),
        'meta' => ['notes' => 'Initial scheduling'],
    ];

    $response = $this->putJson("/api/digital-twin/assets/{$asset->id}/procedures/{$procedure->id}", $payload);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.asset_id', $asset->id)
        ->assertJsonPath('data.maintenance_procedure_id', $procedure->id)
        ->assertJsonPath('data.frequency_value', 90);

    $this->assertDatabaseHas('asset_procedure_links', [
        'asset_id' => $asset->id,
        'maintenance_procedure_id' => $procedure->id,
        'frequency_value' => 90,
        'frequency_unit' => 'day',
    ]);
});
