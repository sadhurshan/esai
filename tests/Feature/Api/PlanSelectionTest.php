<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;

it('allows workspace owners to select a plan', function (): void {
    $plan = Plan::factory()->create([
        'code' => 'community',
        'price_usd' => 0,
    ]);

    $company = Company::factory()->create([
        'plan_id' => null,
        'plan_code' => null,
    ]);

    $user = User::factory()->for($company)->create([
        'role' => 'owner',
    ]);

    $response = $this->actingAs($user)->postJson('/api/company/plan-selection', [
        'plan_code' => $plan->code,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.company.plan', $plan->code)
        ->assertJsonPath('data.plan.code', $plan->code);

    $this->assertDatabaseHas('companies', [
        'id' => $company->id,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $this->assertDatabaseHas('subscriptions', [
        'company_id' => $company->id,
        'stripe_id' => 'sub-free-'.$company->id,
        'stripe_status' => 'active',
    ]);
});

it('rejects plan selection for non owner roles', function (): void {
    $plan = Plan::factory()->create();
    $company = Company::factory()->create([
        'plan_id' => null,
        'plan_code' => null,
    ]);

    $user = User::factory()->for($company)->create([
        'role' => 'buyer_member',
    ]);

    $this->actingAs($user)
        ->postJson('/api/company/plan-selection', ['plan_code' => $plan->code])
        ->assertForbidden();
});
