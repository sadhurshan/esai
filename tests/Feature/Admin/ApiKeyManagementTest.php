<?php

use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

it('allows super admin to manage api keys', function (): void {
    $user = User::factory()->create();
    PlatformAdmin::factory()->super()->for($user)->create();

    actingAs($user);

    $createResponse = postJson('/api/admin/api-keys', [
        'name' => 'Platform Integration',
        'scopes' => ['rfq.read', 'po.write'],
        'expires_at' => now()->addDays(5)->toDateTimeString(),
    ]);

    $createResponse
        ->assertCreated()
        ->assertJsonPath('data.api_key.name', 'Platform Integration')
        ->assertJsonStructure(['data' => ['plain_text_token']]);

    $token = $createResponse->json('data.plain_text_token');
    $prefix = $createResponse->json('data.api_key.token_prefix');

    expect($token)->toStartWith($prefix.'.');
    expect(ApiKey::query()->where('token_prefix', $prefix)->exists())->toBeTrue();

    $listResponse = getJson('/api/admin/api-keys');

    $listResponse
        ->assertOk()
        ->assertJsonMissingPath('data.items.0.plain_text_token');

    $keyId = $createResponse->json('data.api_key.id');

    $rotateResponse = postJson("/api/admin/api-keys/{$keyId}/rotate");

    $rotateResponse
        ->assertOk()
        ->assertJsonStructure(['data' => ['plain_text_token']]);

    $toggleResponse = postJson("/api/admin/api-keys/{$keyId}/toggle", [
        'active' => false,
    ]);

    $toggleResponse
        ->assertOk()
        ->assertJsonPath('data.api_key.active', false);

    deleteJson("/api/admin/api-keys/{$keyId}")
        ->assertOk();

    expect(AuditLog::count())->toBeGreaterThan(0);
});

it('forbids support admin from mutating api keys', function (): void {
    $user = User::factory()->create();
    PlatformAdmin::factory()->support()->for($user)->create();

    actingAs($user);

    $apiKey = ApiKey::factory()->create();

    postJson('/api/admin/api-keys', [
        'name' => 'Support Key',
    ])->assertForbidden();

    postJson("/api/admin/api-keys/{$apiKey->id}/rotate")
        ->assertForbidden();

    postJson("/api/admin/api-keys/{$apiKey->id}/toggle", ['active' => false])
        ->assertForbidden();

    deleteJson("/api/admin/api-keys/{$apiKey->id}")
        ->assertForbidden();
});

it('requires admin guard for api key routes', function (): void {
    $apiKey = ApiKey::factory()->create();

    getJson('/api/admin/api-keys')->assertUnauthorized();
    postJson('/api/admin/api-keys', [])->assertUnauthorized();
    postJson("/api/admin/api-keys/{$apiKey->id}/rotate")
        ->assertUnauthorized();
    postJson("/api/admin/api-keys/{$apiKey->id}/toggle", ['active' => true])
        ->assertUnauthorized();
    deleteJson("/api/admin/api-keys/{$apiKey->id}")
        ->assertUnauthorized();
});
