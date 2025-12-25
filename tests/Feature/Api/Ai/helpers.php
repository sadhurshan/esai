<?php

use App\Models\AiActionDraft;
use App\Models\AiEvent;
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

function createAiEvent(array $attributes = []): AiEvent
{
    $createdAt = $attributes['created_at'] ?? null;
    $updatedAt = $attributes['updated_at'] ?? $createdAt;

    unset($attributes['created_at'], $attributes['updated_at']);

    $event = AiEvent::query()->create(array_merge([
        'feature' => 'forecast',
        'status' => AiEvent::STATUS_SUCCESS,
        'request_json' => [],
        'response_json' => [],
        'latency_ms' => null,
        'error_message' => null,
        'entity_type' => null,
        'entity_id' => null,
    ], $attributes));

    if ($createdAt !== null) {
        $event->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $updatedAt ?? $createdAt,
        ])->save();
    }

    return $event;
}
