<?php

use App\Models\Company;
use App\Models\CompanyFeatureFlag;
use App\Models\Plan;
use App\Models\User;
use App\Services\Auth\AuthResponseFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes plan feature toggles in auth payloads', function (): void {
    $plan = Plan::factory()->create([
        'digital_twin_enabled' => true,
        'maintenance_enabled' => true,
        'inventory_enabled' => true,
        'analytics_enabled' => true,
        'quotes_enabled' => true,
    ]);

    $company = Company::factory()->for($plan, 'plan')->create();
    $user = User::factory()->for($company)->create();

    $payload = app(AuthResponseFactory::class)->make($user);

    expect($payload['feature_flags'] ?? [])
        ->toHaveKey('digital_twin_enabled', true)
        ->toHaveKey('maintenance_enabled', true)
        ->toHaveKey('inventory_enabled', true)
        ->toHaveKey('analytics_enabled', true)
        ->toHaveKey('quotes_enabled', true);
});

it('allows company specific feature flags to override plan defaults', function (): void {
    $plan = Plan::factory()->create([
        'digital_twin_enabled' => true,
        'inventory_enabled' => true,
    ]);

    $company = Company::factory()->for($plan, 'plan')->create();
    $user = User::factory()->for($company)->create();

    CompanyFeatureFlag::factory()->create([
        'company_id' => $company->id,
        'key' => 'digital_twin_enabled',
        'value' => ['enabled' => false],
    ]);

    $payload = app(AuthResponseFactory::class)->make($user);

    expect($payload['feature_flags'] ?? [])
        ->toHaveKey('digital_twin_enabled', false)
        ->toHaveKey('inventory_enabled', true);
});

it('exposes rfq feature flags when the plan allows rfqs', function (): void {
    $plan = Plan::factory()->create([
        'rfqs_per_month' => 25,
    ]);

    $company = Company::factory()->for($plan, 'plan')->create();
    $user = User::factory()->for($company)->create();

    $payload = app(AuthResponseFactory::class)->make($user);
    $flags = $payload['feature_flags'] ?? [];

    expect($flags)
        ->toHaveKey('rfqs.create', true)
        ->toHaveKey('rfqs.publish', true)
        ->toHaveKey('rfqs.suppliers.invite', true)
        ->toHaveKey('rfqs.attachments.manage', true)
        ->toHaveKey('suppliers.directory.browse', true);
});

it('allows rfq feature flags to be overridden per company', function (): void {
    $plan = Plan::factory()->create([
        'rfqs_per_month' => 25,
    ]);

    $company = Company::factory()->for($plan, 'plan')->create();
    $user = User::factory()->for($company)->create();

    CompanyFeatureFlag::factory()->create([
        'company_id' => $company->id,
        'key' => 'rfqs.create',
        'value' => ['enabled' => false],
    ]);

    $payload = app(AuthResponseFactory::class)->make($user);

    expect($payload['feature_flags'] ?? [])
        ->toHaveKey('rfqs.create', false)
        ->toHaveKey('rfqs.publish', true);
});

it('enables purchase orders for growth plans and above', function (): void {
    $growthPlan = Plan::factory()->create([
        'code' => 'growth',
    ]);

    $growthCompany = Company::factory()->for($growthPlan, 'plan')->create();
    $growthUser = User::factory()->for($growthCompany)->create();

    $growthPayload = app(AuthResponseFactory::class)->make($growthUser);

    expect($growthPayload['feature_flags'] ?? [])->toHaveKey('purchase_orders', true);

    $starterPlan = Plan::factory()->create([
        'code' => 'starter',
    ]);

    $starterCompany = Company::factory()->for($starterPlan, 'plan')->create();
    $starterUser = User::factory()->for($starterCompany)->create();

    $starterPayload = app(AuthResponseFactory::class)->make($starterUser);

    expect($starterPayload['feature_flags'] ?? [])->toHaveKey('purchase_orders', false);
});

it('enables invoices for growth plans and above', function (): void {
    $growthPlan = Plan::factory()->create([
        'code' => 'growth',
    ]);

    $growthCompany = Company::factory()->for($growthPlan, 'plan')->create();
    $growthUser = User::factory()->for($growthCompany)->create();

    $growthPayload = app(AuthResponseFactory::class)->make($growthUser);

    expect($growthPayload['feature_flags'] ?? [])->toHaveKey('invoices_enabled', true);

    $starterPlan = Plan::factory()->create([
        'code' => 'starter',
    ]);

    $starterCompany = Company::factory()->for($starterPlan, 'plan')->create();
    $starterUser = User::factory()->for($starterCompany)->create();

    $starterPayload = app(AuthResponseFactory::class)->make($starterUser);

    expect($starterPayload['feature_flags'] ?? [])->toHaveKey('invoices_enabled', false);
});

it('enables digital twins for growth plans and above', function (): void {
    $growthPlan = Plan::factory()->create([
        'code' => 'growth',
        'digital_twin_enabled' => false,
    ]);

    $growthCompany = Company::factory()->for($growthPlan, 'plan')->create();
    $growthUser = User::factory()->for($growthCompany)->create();

    $growthPayload = app(AuthResponseFactory::class)->make($growthUser);

    expect($growthPayload['feature_flags'] ?? [])->toHaveKey('digital_twin_enabled', true);

    $starterPlan = Plan::factory()->create([
        'code' => 'starter',
        'digital_twin_enabled' => false,
    ]);

    $starterCompany = Company::factory()->for($starterPlan, 'plan')->create();
    $starterUser = User::factory()->for($starterCompany)->create();

    $starterPayload = app(AuthResponseFactory::class)->make($starterUser);

    expect($starterPayload['feature_flags'] ?? [])->toHaveKey('digital_twin_enabled', false);
});
