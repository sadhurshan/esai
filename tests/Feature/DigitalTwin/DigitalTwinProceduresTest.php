<?php

require_once __DIR__.'/DigitalTwinTestHelpers.php';

use App\Models\MaintenanceProcedure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('lists maintenance procedures for the active company only', function () {
    $user = createDigitalTwinUser();
    $company = $user->company;

    MaintenanceProcedure::factory()->count(2)->for($company)->create();
    MaintenanceProcedure::factory()->create();

    $response = $this->getJson('/api/digital-twin/procedures');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data.items');
});

it('creates a maintenance procedure with steps and metadata', function () {
    $user = createDigitalTwinUser();

    $payload = [
        'code' => 'MP-200',
        'title' => 'Filter Replacement',
        'category' => 'preventive',
        'estimated_minutes' => 60,
        'instructions_md' => 'Do the thing.',
        'tools' => ['wrench'],
        'safety' => ['gloves'],
        'steps' => [
            [
                'step_no' => 1,
                'title' => 'Lock out',
                'instruction_md' => 'Turn off power.',
            ],
        ],
    ];

    $response = $this->postJson('/api/digital-twin/procedures', $payload);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.code', 'MP-200');

    $this->assertDatabaseHas('maintenance_procedures', [
        'code' => 'MP-200',
        'company_id' => $user->company_id,
    ]);
});

it('rejects maintenance procedure creation for users without manage permissions', function () {
    $user = createDigitalTwinUser(overrides: ['role' => 'buyer_requester']);

    $payload = [
        'code' => 'MP-403',
        'title' => 'Not Allowed',
        'category' => 'safety',
        'instructions_md' => 'N/A',
        'steps' => [
            [
                'step_no' => 1,
                'title' => 'n/a',
                'instruction_md' => 'n/a',
            ],
        ],
    ];

    $response = $this->postJson('/api/digital-twin/procedures', $payload);

    $response->assertForbidden()
        ->assertJsonPath('message', 'This action is unauthorized.');
});

it('soft deletes a maintenance procedure', function () {
    $user = createDigitalTwinUser();
    $procedure = MaintenanceProcedure::factory()->for($user->company)->create();

    $response = $this->deleteJson("/api/digital-twin/procedures/{$procedure->id}");

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Maintenance procedure removed.');

    $this->assertSoftDeleted('maintenance_procedures', ['id' => $procedure->id]);
});

it('prevents deleting a procedure that belongs to another company', function () {
    $owner = createDigitalTwinUser();
    $procedure = MaintenanceProcedure::factory()->for($owner->company)->create();

    $otherUser = createDigitalTwinUser();
    actingAs($otherUser);

    $response = $this->deleteJson("/api/digital-twin/procedures/{$procedure->id}");

    $response->assertForbidden()
        ->assertJsonPath('message', 'This action is unauthorized.');

    $this->assertDatabaseHas('maintenance_procedures', ['id' => $procedure->id, 'deleted_at' => null]);
});
