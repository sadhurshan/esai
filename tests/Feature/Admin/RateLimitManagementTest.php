<?php

use App\Enums\RateLimitScope;
use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\RateLimit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

it('allows super admin to manage rate limits', function (): void {
    $user = User::factory()->create();
    PlatformAdmin::factory()->super()->for($user)->create();

    actingAs($user);

    $createResponse = postJson('/api/admin/rate-limits', [
        'window_seconds' => 60,
        'max_requests' => 50,
        'scope' => RateLimitScope::Api->value,
    ]);

    $createResponse
        ->assertCreated()
        ->assertJsonPath('data.rate_limit.max_requests', 50);

    $limitId = $createResponse->json('data.rate_limit.id');

    $updateResponse = putJson("/api/admin/rate-limits/{$limitId}", [
        'max_requests' => 75,
    ]);

    $updateResponse
        ->assertOk()
        ->assertJsonPath('data.rate_limit.max_requests', 75);

    $listResponse = getJson('/api/admin/rate-limits');

    $listResponse
        ->assertOk()
        ->assertJsonPath('data.items.0.id', $limitId);

    deleteJson("/api/admin/rate-limits/{$limitId}")
        ->assertOk();
});

it('forbids support admin from mutating rate limits', function (): void {
    $user = User::factory()->create();
    PlatformAdmin::factory()->support()->for($user)->create();

    actingAs($user);

    $limit = RateLimit::factory()->create();

    postJson('/api/admin/rate-limits', [
        'window_seconds' => 60,
        'max_requests' => 10,
        'scope' => RateLimitScope::Api->value,
    ])->assertForbidden();

    putJson("/api/admin/rate-limits/{$limit->id}", [
        'max_requests' => 200,
    ])->assertForbidden();

    deleteJson("/api/admin/rate-limits/{$limit->id}")
        ->assertForbidden();
});

it('requires admin guard for rate limit routes', function (): void {
    $limit = RateLimit::factory()->create();

    getJson('/api/admin/rate-limits')->assertUnauthorized();
    postJson('/api/admin/rate-limits', [])->assertUnauthorized();
    putJson("/api/admin/rate-limits/{$limit->id}", [])->assertUnauthorized();
    deleteJson("/api/admin/rate-limits/{$limit->id}")->assertUnauthorized();
});

it('enforces rate limits per company scope', function (): void {
    Route::middleware(['rate.limit.enforcer:api'])->get('/test-rate-limit', static fn () => response()->json(['status' => 'ok']));

    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    PlatformAdmin::factory()->super()->for($user)->create();

    RateLimit::factory()->create([
        'company_id' => $company->id,
        'window_seconds' => 60,
        'max_requests' => 2,
        'scope' => RateLimitScope::Api,
    ]);

    actingAs($user);

    getJson('/test-rate-limit')->assertOk();
    getJson('/test-rate-limit')->assertOk();
    getJson('/test-rate-limit')->assertStatus(429);
});
