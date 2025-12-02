<?php

require_once __DIR__.'/DigitalTwinTestHelpers.php';

use App\Models\Asset;
use App\Models\Location;
use App\Models\MaintenanceProcedure;
use App\Models\ProcedureStep;
use App\Models\System;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
