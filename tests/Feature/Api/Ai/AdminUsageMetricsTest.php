<?php

require_once __DIR__ . '/helpers.php';

use App\Models\AiActionDraft;
use App\Models\AiEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('ai.enabled', true);
    config()->set('ai.shared_secret', 'admin-metrics-secret');
});

it('returns usage metrics for ai admins', function (): void {
    Carbon::setTestNow(Carbon::parse('2025-01-31 12:00:00'));

    ['user' => $user, 'company' => $company] = provisionCopilotActionUser(role: 'buyer_admin');

    $insideWindow = fn (int $days): Carbon => Carbon::now()->copy()->subDays($days);

    AiActionDraft::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'created_at' => $insideWindow(5),
    ]);

    AiActionDraft::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'created_at' => $insideWindow(7),
    ]);

    AiActionDraft::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'status' => AiActionDraft::STATUS_APPROVED,
        'approved_by' => $user->id,
        'approved_at' => $insideWindow(3),
        'created_at' => $insideWindow(4),
    ]);

    AiActionDraft::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'created_at' => Carbon::now()->subDays(45),
    ]);

    AiActionDraft::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'status' => AiActionDraft::STATUS_APPROVED,
        'approved_by' => $user->id,
        'approved_at' => Carbon::now()->subDays(50),
        'created_at' => Carbon::now()->subDays(52),
    ]);

    createAiEvent([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'feature' => 'forecast',
        'status' => AiEvent::STATUS_SUCCESS,
        'created_at' => $insideWindow(2),
    ]);

    createAiEvent([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'feature' => 'forecast',
        'status' => AiEvent::STATUS_SUCCESS,
        'created_at' => $insideWindow(1),
    ]);

    createAiEvent([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'feature' => 'forecast',
        'status' => AiEvent::STATUS_ERROR,
        'created_at' => $insideWindow(1),
    ]);

    createAiEvent([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'feature' => 'workspace_help',
        'status' => AiEvent::STATUS_SUCCESS,
        'created_at' => $insideWindow(4),
    ]);

    createAiEvent([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'feature' => 'workspace_help',
        'status' => AiEvent::STATUS_SUCCESS,
        'created_at' => Carbon::now()->subDays(40),
    ]);

    createAiEvent([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'feature' => 'ai_chat_tool_resolve',
        'status' => AiEvent::STATUS_ERROR,
        'created_at' => $insideWindow(3),
    ]);

    createAiEvent([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'feature' => 'copilot_action_plan',
        'status' => AiEvent::STATUS_ERROR,
        'created_at' => $insideWindow(2),
    ]);

    createAiEvent([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'feature' => 'copilot_action_plan',
        'status' => AiEvent::STATUS_ERROR,
        'created_at' => Carbon::now()->subDays(60),
    ]);

    actingAs($user);

    $response = $this->getJson('/api/v1/ai/admin/usage-metrics');

    $response->assertOk()
        ->assertJsonPath('data.metrics.actions.planned', 3)
        ->assertJsonPath('data.metrics.actions.approved', 1)
        ->assertJsonPath('data.metrics.forecasts.generated', 2)
        ->assertJsonPath('data.metrics.forecasts.errors', 1)
        ->assertJsonPath('data.metrics.help_requests.total', 1)
        ->assertJsonPath('data.metrics.tool_errors.total', 3);

    expect($response->json('data.metrics.window_days'))->toBe(30);

    Carbon::setTestNow();
});

it('rejects users without ai admin access', function (): void {
    ['user' => $user] = provisionCopilotActionUser(role: 'buyer_requester');

    actingAs($user);

    $this->getJson('/api/v1/ai/admin/usage-metrics')
        ->assertForbidden()
        ->assertJsonPath('message', 'AI admin permission required.');
});
