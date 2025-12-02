<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

function provisionExportUser(string $role): array
{
    $plan = Plan::factory()->create([
        'code' => 'community',
        'price_usd' => 0,
        'exports_enabled' => true,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => $role,
    ]);

    return [$company, $user];
}

test('users with orders permission can access exports index', function (): void {
    [, $user] = provisionExportUser('buyer_member');

    actingAs($user);

    getJson('/api/exports')
        ->assertOk()
        ->assertJsonPath('status', 'success');
});

test('users without orders permission cannot access exports', function (): void {
    [, $user] = provisionExportUser('supplier_estimator');

    actingAs($user);

    getJson('/api/exports')
        ->assertForbidden()
        ->assertJsonPath('message', 'Orders access required to run exports.');
});
