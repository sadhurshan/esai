<?php

use App\Models\Company;
use App\Models\CompanyFeatureFlag;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

it('allows super admin to create update list and delete company feature flag', function (): void {
    $company = Company::factory()->create();

    $user = User::factory()->create();
    PlatformAdmin::factory()->super()->for($user)->create();

    actingAs($user);

    $createResponse = postJson("/api/admin/companies/{$company->id}/feature-flags", [
        'key' => 'beta_dashboard',
        'value' => [
            'enabled' => true,
            'rollout' => 50,
        ],
    ]);

    $createResponse
        ->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.feature_flag.key', 'beta_dashboard');

    $flagId = $createResponse->json('data.feature_flag.id');

    $updateResponse = putJson("/api/admin/companies/{$company->id}/feature-flags/{$flagId}", [
        'value' => [
            'enabled' => false,
            'notes' => 'Disabled after incident',
        ],
    ]);

    $updateResponse
        ->assertOk()
        ->assertJsonPath('data.feature_flag.value.enabled', false);

    $listResponse = getJson("/api/admin/companies/{$company->id}/feature-flags");

    $listResponse
        ->assertOk()
        ->assertJsonPath('data.items.0.id', $flagId);

    $deleteResponse = deleteJson("/api/admin/companies/{$company->id}/feature-flags/{$flagId}");

    $deleteResponse
        ->assertOk()
        ->assertJsonPath('message', 'Feature flag deleted.');

    expect(CompanyFeatureFlag::query()->whereKey($flagId)->exists())->toBeFalse();
});

it('forbids support admin from mutating feature flags', function (): void {
    $company = Company::factory()->create();
    $flag = CompanyFeatureFlag::factory()->for($company)->create([
        'key' => 'beta_dashboard',
    ]);

    $user = User::factory()->create();
    PlatformAdmin::factory()->support()->for($user)->create();

    actingAs($user);

    postJson("/api/admin/companies/{$company->id}/feature-flags", [
        'key' => 'new_flag',
    ])->assertForbidden();

    putJson("/api/admin/companies/{$company->id}/feature-flags/{$flag->id}", [
        'value' => ['enabled' => false],
    ])->assertForbidden();

    deleteJson("/api/admin/companies/{$company->id}/feature-flags/{$flag->id}")
        ->assertForbidden();
});

it('requires admin guard for feature flag routes', function (): void {
    $company = Company::factory()->create();
    $flag = CompanyFeatureFlag::factory()->for($company)->create();

    getJson("/api/admin/companies/{$company->id}/feature-flags")
        ->assertUnauthorized();

    postJson("/api/admin/companies/{$company->id}/feature-flags", [])
        ->assertUnauthorized();

    putJson("/api/admin/companies/{$company->id}/feature-flags/{$flag->id}", [])
        ->assertUnauthorized();

    deleteJson("/api/admin/companies/{$company->id}/feature-flags/{$flag->id}")
        ->assertUnauthorized();
});
