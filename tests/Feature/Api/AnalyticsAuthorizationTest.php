<?php

use App\Http\Middleware\EnsureAnalyticsAccess;
use App\Models\AnalyticsSnapshot;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function analyticsTestContext(string $role = 'buyer_admin'): array {
    $plan = Plan::factory()->create([
        'analytics_enabled' => true,
        'analytics_history_months' => 12,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => $role,
    ]);

    return compact('plan', 'company', 'user');
}

function seedAnalyticsSnapshot(Company $company): void {
    AnalyticsSnapshot::create([
        'company_id' => $company->id,
        'type' => AnalyticsSnapshot::TYPE_SPEND,
        'period_start' => Carbon::now()->startOfMonth()->toDateString(),
        'period_end' => Carbon::now()->endOfMonth()->toDateString(),
        'value' => 125000.00,
        'meta' => ['currency' => 'USD'],
    ]);
}

it('allows analytics overview when the user has analytics.read permission', function (): void {
    ['company' => $company, 'user' => $user] = analyticsTestContext('buyer_admin');

    seedAnalyticsSnapshot($company);

    $this->withoutMiddleware([EnsureAnalyticsAccess::class]);
    $this->actingAs($user);

    $response = $this->getJson('/api/analytics/overview');

    $response->assertOk()
        ->assertJsonPath('message', 'Analytics overview retrieved.');
});

it('denies analytics overview when the user lacks analytics permissions', function (): void {
    ['company' => $company, 'user' => $user] = analyticsTestContext('supplier_admin');

    $this->withoutMiddleware([EnsureAnalyticsAccess::class]);
    $this->actingAs($user);

    $response = $this->getJson('/api/analytics/overview');

    $response->assertForbidden()
        ->assertJsonPath('message', 'Analytics role required.')
        ->assertJsonPath('errors.code', 'analytics_role_required');
});

it('denies copilot analytics queries when the user lacks analytics permissions', function (): void {
    ['company' => $company, 'user' => $user] = analyticsTestContext('supplier_estimator');

    seedAnalyticsSnapshot($company);

    $this->withoutMiddleware([EnsureAnalyticsAccess::class]);
    $this->actingAs($user);

    $response = $this->postJson('/api/copilot/analytics', [
        'query' => 'show spend metrics',
    ]);

    $response->assertForbidden()
        ->assertJsonPath('message', 'Analytics role required.')
        ->assertJsonPath('errors.code', 'analytics_role_required');
});
