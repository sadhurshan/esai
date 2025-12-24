<?php

require_once __DIR__ . '/helpers.php';

use App\Models\AiActionDraft;
use App\Models\AiActionFeedback;
use App\Models\AiEvent;
use App\Models\InventoryWhatIfSnapshot;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\Converters\AiDraftConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('ai.enabled', true);
    config()->set('ai.shared_secret', 'test-secret');
});

it('creates a draft when planning an action', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotActionUser();

    $client = Mockery::mock(AiClient::class);
    $client->shouldReceive('planAction')
        ->once()
        ->andReturn([
            'status' => 'success',
            'message' => 'ok',
            'data' => [
                'action_type' => AiActionDraft::TYPE_RFQ_DRAFT,
                'summary' => 'Draft ready',
                'payload' => [
                    'line_items' => [],
                ],
                'citations' => [],
                'needs_human_review' => false,
                'warnings' => [],
            ],
            'errors' => [],
        ]);
    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $payload = [
        'action_type' => AiActionDraft::TYPE_RFQ_DRAFT,
        'query' => 'Draft an RFQ',
        'inputs' => ['category' => 'CNC'],
    ];

    $response = $this->postJson('/api/v1/ai/actions/plan', $payload);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.draft.action_type', AiActionDraft::TYPE_RFQ_DRAFT)
        ->assertJsonPath('data.draft.status', AiActionDraft::STATUS_DRAFTED);

    expect(AiActionDraft::query()->where('company_id', $company->id)->count())->toBe(1);
    expect(AiEvent::query()->where('feature', 'copilot_action_plan')->count())->toBe(1);
});

it('prevents unauthorized roles from planning actions', function (): void {
    ['user' => $user] = provisionCopilotActionUser(role: 'finance');

    $client = Mockery::mock(AiClient::class);
    $client->shouldNotReceive('planAction');
    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $response = $this->postJson('/api/v1/ai/actions/plan', [
        'action_type' => AiActionDraft::TYPE_RFQ_DRAFT,
        'query' => 'Draft an RFQ',
    ]);

    $response->assertForbidden()
        ->assertJsonPath('status', 'error');

    expect(AiActionDraft::count())->toBe(0);
});

it('prevents unauthorized roles from planning invoice actions', function (string $actionType): void {
    ['user' => $user] = provisionCopilotActionUser(role: 'buyer_requester');

    $client = Mockery::mock(AiClient::class);
    $client->shouldNotReceive('planAction');
    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $response = $this->postJson('/api/v1/ai/actions/plan', [
        'action_type' => $actionType,
        'query' => 'Prepare invoice follow-up',
    ]);

    $response->assertForbidden()
        ->assertJsonPath('message', 'You are not authorized to run this Copilot action.');

    expect(AiActionDraft::count())->toBe(0);
})->with([
    'invoice_draft' => [AiActionDraft::TYPE_INVOICE_DRAFT],
    'approve_invoice' => [AiActionDraft::TYPE_APPROVE_INVOICE],
]);

it('blocks draft approval when the user lacks access', function (): void {
    ['user' => $user] = provisionCopilotActionUser(role: 'finance');

    actingAs($user);

    $draft = createDraftForUser($user, [
        'action_type' => AiActionDraft::TYPE_RFQ_DRAFT,
    ]);

    $response = $this->postJson("/api/v1/ai/actions/{$draft->id}/approve");

    $response->assertForbidden()
        ->assertJsonPath('status', 'error');

    expect($draft->fresh()->status)->toBe(AiActionDraft::STATUS_DRAFTED);
});

it('prevents unauthorized roles from approving invoice actions', function (string $actionType): void {
    ['user' => $user] = provisionCopilotActionUser(role: 'buyer_requester');

    $draft = createDraftForUser($user, [
        'action_type' => $actionType,
    ]);

    actingAs($user);

    $response = $this->postJson("/api/v1/ai/actions/{$draft->id}/approve");

    $response->assertForbidden()
        ->assertJsonPath('message', 'You are not authorized to approve this Copilot action.');

    expect($draft->fresh()->status)->toBe(AiActionDraft::STATUS_DRAFTED);
})->with([
    'invoice_draft' => [AiActionDraft::TYPE_INVOICE_DRAFT],
    'approve_invoice' => [AiActionDraft::TYPE_APPROVE_INVOICE],
]);

it('requires approval before converters run', function (): void {
    ['user' => $user] = provisionCopilotActionUser();

    $draft = createDraftForUser($user, [
        'action_type' => AiActionDraft::TYPE_RFQ_DRAFT,
    ]);

    $converter = Mockery::mock(AiDraftConversionService::class);
    $converter->shouldReceive('convert')
        ->once()
        ->andThrow(ValidationException::withMessages([
            'payload' => ['invalid'],
        ]));
    $this->app->instance(AiDraftConversionService::class, $converter);

    actingAs($user);

    $response = $this->postJson("/api/v1/ai/actions/{$draft->id}/approve");

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Draft payload is invalid.');

    expect($draft->fresh()->status)->toBe(AiActionDraft::STATUS_DRAFTED);
});

it('converts the draft after approval', function (): void {
    ['user' => $user] = provisionCopilotActionUser();

    $draft = createDraftForUser($user, [
        'action_type' => AiActionDraft::TYPE_RFQ_DRAFT,
    ]);

    $converter = Mockery::mock(AiDraftConversionService::class);
    $converter->shouldReceive('convert')
        ->once()
        ->andReturn(['entity' => ['id' => 99]]);
    $this->app->instance(AiDraftConversionService::class, $converter);

    actingAs($user);

    $response = $this->postJson("/api/v1/ai/actions/{$draft->id}/approve");

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.draft.status', AiActionDraft::STATUS_APPROVED);

    $draft->refresh();

    expect($draft->approved_by)->toBe($user->id)
        ->and($draft->isApproved())->toBeTrue();
});

it('creates an inventory what-if snapshot when approving a draft', function (): void {
    ['user' => $user] = provisionCopilotActionUser();

    $output = [
        'action_type' => AiActionDraft::TYPE_INVENTORY_WHATIF,
        'summary' => 'Scenario ready',
        'payload' => [
            'projected_stockout_risk' => 0.25,
            'expected_stockout_days' => 2,
            'expected_holding_cost_change' => -1500.5,
            'recommendation' => 'Increase safety stock by 10%',
            'assumptions' => ['Based on 90-day forecast'],
        ],
        'citations' => [[
            'doc_id' => 'inv-1',
            'doc_version' => 'v1',
            'chunk_id' => 'chunk-1',
            'score' => 0.92,
            'snippet' => 'Inventory policy note',
        ]],
        'warnings' => ['Lead time volatility detected'],
        'confidence' => 0.81,
        'needs_human_review' => false,
    ];

    $draft = createDraftForUser($user, [
        'action_type' => AiActionDraft::TYPE_INVENTORY_WHATIF,
        'output_json' => $output,
        'input_json' => [
            'query' => 'Run what-if scenario',
            'inputs' => [
                'scenario_name' => 'Safety stock tweak',
                'part_identifier' => 'SKU-100',
            ],
            'filters' => [],
            'user_context' => [],
        ],
        'citations_json' => $output['citations'],
    ]);

    actingAs($user);

    $response = $this->postJson("/api/v1/ai/actions/{$draft->id}/approve");

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.draft.status', AiActionDraft::STATUS_APPROVED);

    $snapshot = InventoryWhatIfSnapshot::query()->first();

    expect($snapshot)->not->toBeNull();
    expect(optional($snapshot)->recommendation)->toBe('Increase safety stock by 10%');

    $draft->refresh();

    expect($draft->entity_type)->toBe((new InventoryWhatIfSnapshot())->getMorphClass())
        ->and($draft->entity_id)->toBe(optional($snapshot)->id);
});

it('rejects a draft without creating downstream entities', function (): void {
    ['user' => $user] = provisionCopilotActionUser();

    $draft = createDraftForUser($user, [
        'action_type' => AiActionDraft::TYPE_INVENTORY_WHATIF,
    ]);

    actingAs($user);

    $response = $this->postJson("/api/v1/ai/actions/{$draft->id}/reject", [
        'reason' => 'Needs additional review',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.draft.status', AiActionDraft::STATUS_REJECTED);

    $draft->refresh();

    expect($draft->status)->toBe(AiActionDraft::STATUS_REJECTED)
        ->and($draft->rejected_reason)->toBe('Needs additional review')
        ->and($draft->entity_type)->toBeNull()
        ->and($draft->entity_id)->toBeNull();

    expect(InventoryWhatIfSnapshot::count())->toBe(0);
});

it('records feedback for a draft', function (): void {
    ['user' => $user] = provisionCopilotActionUser();

    $draft = createDraftForUser($user, [
        'action_type' => AiActionDraft::TYPE_RFQ_DRAFT,
    ]);

    actingAs($user);

    $response = $this->postJson("/api/v1/ai/actions/{$draft->id}/feedback", [
        'rating' => 4,
        'comment' => 'Grounded output',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.feedback.rating', 4);

    expect(AiActionFeedback::query()->where('ai_action_draft_id', $draft->id)->count())->toBe(1);
});

it('validates rating bounds when recording feedback', function (): void {
    ['user' => $user] = provisionCopilotActionUser();

    $draft = createDraftForUser($user, [
        'action_type' => AiActionDraft::TYPE_RFQ_DRAFT,
    ]);

    actingAs($user);

    $response = $this->postJson("/api/v1/ai/actions/{$draft->id}/feedback", [
        'rating' => 10,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('errors.rating.0', 'The rating field must not be greater than 5.');
});

it('prevents unauthorized users from submitting feedback', function (): void {
    ['user' => $user] = provisionCopilotActionUser(role: 'finance');

    $draft = createDraftForUser($user, [
        'action_type' => AiActionDraft::TYPE_RFQ_DRAFT,
    ]);

    actingAs($user);

    $response = $this->postJson("/api/v1/ai/actions/{$draft->id}/feedback", [
        'rating' => 3,
        'comment' => 'Needs work',
    ]);

    $response->assertForbidden()
        ->assertJsonPath('status', 'error');
});
