<?php

require_once __DIR__.'/DigitalTwinTestHelpers.php';

use App\Models\Asset;
use App\Models\Location;
use App\Models\System;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('lists assets for the active company with the standard envelope', function () {
    $user = createDigitalTwinUser();
    $company = $user->company;

    $location = Location::factory()->for($company)->create();
    $system = System::factory()->for($company)->for($location)->create();

    Asset::factory()->count(2)->for($company)->create([
        'location_id' => $location->id,
        'system_id' => $system->id,
    ]);

    $response = $this->getJson('/api/digital-twin/assets');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data.items');

    $this->assertEquals($company->id, $response->json('data.items')[0]['company_id']);
});

it('creates an asset scoped to the resolved company', function () {
    $user = createDigitalTwinUser();
    $company = $user->company;

    $location = Location::factory()->for($company)->create();
    $system = System::factory()->for($company)->for($location)->create();

    $payload = [
        'name' => 'Inline Pump',
        'tag' => 'P-100',
        'status' => 'standby',
        'location_id' => $location->id,
        'system_id' => $system->id,
    ];

    $response = $this->postJson('/api/digital-twin/assets', $payload);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.company_id', $company->id)
        ->assertJsonPath('data.status', 'standby');

    $this->assertDatabaseHas('assets', [
        'name' => 'Inline Pump',
        'company_id' => $company->id,
    ]);
});

it('rejects asset creation when the actor lacks manage permissions', function () {
    $user = createDigitalTwinUser(overrides: ['role' => 'buyer_requester']);
    $company = $user->company;
    $location = Location::factory()->for($company)->create();

    $payload = [
        'name' => 'Unauthorized Asset',
        'location_id' => $location->id,
    ];

    $response = $this->postJson('/api/digital-twin/assets', $payload);

    $response->assertForbidden()
        ->assertJsonPath('message', 'This action is unauthorized.');
});

it('soft deletes an asset and emits the success envelope', function () {
    $user = createDigitalTwinUser();
    $asset = Asset::factory()->for($user->company)->create();

    $response = $this->deleteJson("/api/digital-twin/assets/{$asset->id}");

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Asset removed.');

    $this->assertSoftDeleted('assets', ['id' => $asset->id]);
});

it('prevents deleting an asset that belongs to another company', function () {
    $owner = createDigitalTwinUser();
    $asset = Asset::factory()->for($owner->company)->create();

    $otherUser = createDigitalTwinUser();
    actingAs($otherUser);

    $response = $this->deleteJson("/api/digital-twin/assets/{$asset->id}");

    $response->assertForbidden()
        ->assertJsonPath('message', 'This action is unauthorized.');

    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'deleted_at' => null]);
});

it('updates an asset status through the status endpoint', function () {
    $user = createDigitalTwinUser();
    $asset = Asset::factory()->for($user->company)->create(['status' => 'active']);

    $response = $this->patchJson("/api/digital-twin/assets/{$asset->id}/status", [
        'status' => 'maintenance',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', 'maintenance');

    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'status' => 'maintenance']);
});

it('blocks asset status updates from other companies', function () {
    $owner = createDigitalTwinUser();
    $asset = Asset::factory()->for($owner->company)->create(['status' => 'active']);

    $otherUser = createDigitalTwinUser();
    actingAs($otherUser);

    $response = $this->patchJson("/api/digital-twin/assets/{$asset->id}/status", [
        'status' => 'maintenance',
    ]);

    $response->assertForbidden()
        ->assertJsonPath('message', 'This action is unauthorized.');

    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'status' => 'active']);
});
