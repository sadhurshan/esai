<?php

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);
it('allows super admin to create update list and delete plan', function (): void {
    $user = User::factory()->create();
    PlatformAdmin::factory()->super()->for($user)->create();

    actingAs($user);

    $createResponse = postJson('/api/admin/plans', [
        'code' => 'pro',
        'name' => 'Pro Plan',
        'price_usd' => 199.99,
        'rfqs_per_month' => 200,
        'invoices_per_month' => 150,
        'users_max' => 50,
        'storage_gb' => 250,
        'erp_integrations_max' => 2,
        'analytics_enabled' => true,
    ]);

    $createResponse
        ->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.plan.code', 'pro');

    $planId = $createResponse->json('data.plan.id');

    $updateResponse = putJson("/api/admin/plans/{$planId}", [
        'name' => 'Pro Plus',
        'rfqs_per_month' => 250,
    ]);

    $updateResponse
        ->assertOk()
        ->assertJsonPath('data.plan.name', 'Pro Plus')
        ->assertJsonPath('data.plan.rfqs_per_month', 250);

    $listResponse = getJson('/api/admin/plans');

    $listResponse
        ->assertOk()
        ->assertJsonPath('data.items.0.id', $planId);

    $deleteResponse = deleteJson("/api/admin/plans/{$planId}");

    $deleteResponse
        ->assertOk()
        ->assertJsonPath('message', 'Plan deleted.');

    expect(Plan::query()->whereKey($planId)->exists())->toBeFalse();
});

it('forbids support admin from mutating plans', function (): void {
    $user = User::factory()->create();
    PlatformAdmin::factory()->support()->for($user)->create();

    actingAs($user);

    $plan = Plan::factory()->create();

    postJson('/api/admin/plans', [
        'code' => 'enterprise',
        'name' => 'Enterprise',
        'price_usd' => 499,
        'rfqs_per_month' => 1000,
        'invoices_per_month' => 900,
        'users_max' => 500,
        'storage_gb' => 1024,
    ])->assertForbidden();

    putJson("/api/admin/plans/{$plan->id}", ['name' => 'Updated'])
        ->assertForbidden();

    deleteJson("/api/admin/plans/{$plan->id}")
        ->assertForbidden();
});

it('requires admin guard for plan routes', function (): void {
    $plan = Plan::factory()->create();

    getJson('/api/admin/plans')->assertUnauthorized();
    postJson('/api/admin/plans', [])->assertUnauthorized();
    putJson("/api/admin/plans/{$plan->id}", [])->assertUnauthorized();
});

it('allows super admin to assign plan and update status', function (): void {
    $plan = Plan::factory()->create(['code' => 'growth']);
    $company = Company::factory()->create([
        'status' => CompanyStatus::Pending->value,
    ]);

    $user = User::factory()->create();
    PlatformAdmin::factory()->super()->for($user)->create();

    actingAs($user);

    $assignResponse = postJson("/api/admin/companies/{$company->id}/assign-plan", [
        'plan_id' => $plan->id,
        'trial_ends_at' => now()->addDays(14)->toDateString(),
    ]);

    $assignResponse
        ->assertAccepted()
        ->assertJsonPath('data.company.plan.id', $plan->id);

    $statusResponse = putJson("/api/admin/companies/{$company->id}/status", [
        'status' => CompanyStatus::Active->value,
        'notes' => 'Activated by admin',
    ]);

    $statusResponse
        ->assertOk()
        ->assertJsonPath('data.company.status', CompanyStatus::Active->value)
        ->assertJsonPath('data.company.notes', 'Activated by admin');
});

it('forbids support admin from mutating company plan or status', function (): void {
    $plan = Plan::factory()->create(['code' => 'growth']);
    $company = Company::factory()->create([
        'status' => CompanyStatus::Pending->value,
    ]);

    $user = User::factory()->create();
    PlatformAdmin::factory()->support()->for($user)->create();

    actingAs($user);

    postJson("/api/admin/companies/{$company->id}/assign-plan", [
        'plan_id' => $plan->id,
    ])->assertForbidden();

    putJson("/api/admin/companies/{$company->id}/status", [
        'status' => CompanyStatus::Active->value,
    ])->assertForbidden();
});