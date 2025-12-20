<?php

use App\Models\AiActionDraft;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;

function provisionCopilotActionUser(string $role = 'buyer_admin'): array
{
    $plan = Plan::factory()->create([
        'code' => 'community',
        'price_usd' => null,
    ]);

    $company = Company::factory()
        ->for($plan)
        ->create([
            'status' => 'active',
        ]);

    $user = User::factory()->for($company)->create([
        'role' => $role,
    ]);

    return compact('user', 'company');
}

function createDraftForUser(User $user, array $attributes = []): AiActionDraft
{
    return AiActionDraft::factory()
        ->state(array_merge([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
        ], $attributes))
        ->create();
}
