<?php

use App\Enums\DigitalTwinAuditEvent as DigitalTwinAuditEventEnum;
use App\Models\DigitalTwin;
use App\Models\DigitalTwinAuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('returns audit events for a digital twin to super admins', function () {
    $admin = User::factory()->create(['role' => 'platform_super']);
    $twin = DigitalTwin::factory()->create();

    $events = DigitalTwinAuditEvent::factory()
        ->count(3)
        ->state(function () use ($twin, $admin) {
            return [
                'digital_twin_id' => $twin->id,
                'actor_id' => $admin->id,
                'event' => DigitalTwinAuditEventEnum::Updated,
                'meta' => ['changed' => ['title']],
            ];
        })
        ->create();

    actingAs($admin);

    $response = $this->getJson("/api/admin/digital-twins/{$twin->id}/audit-events");

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount($events->count(), 'data.items')
        ->assertJsonStructure([
            'data' => [
                'items' => [
                    ['id', 'event', 'meta', 'actor', 'created_at'],
                ],
                'meta' => ['next_cursor', 'prev_cursor', 'per_page'],
            ],
        ]);
});

it('blocks non platform admins from viewing audit events', function () {
    $user = User::factory()->create(['role' => 'buyer_admin']);
    $twin = DigitalTwin::factory()->create();

    actingAs($user);

    $response = $this->getJson("/api/admin/digital-twins/{$twin->id}/audit-events");

    $response->assertForbidden();
});
