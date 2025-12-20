<?php

require_once __DIR__ . '/helpers.php';

use App\Models\AiEvent;
use App\Models\AiWorkflow;
use App\Models\AiWorkflowStep;
use App\Models\CompanyFeatureFlag;
use App\Services\Ai\AiClient;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('ai.enabled', true);
    config()->set('ai.shared_secret', 'test-secret');
});

it('starts a workflow and persists steps', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotActionUser();

    $client = Mockery::mock(AiClient::class);
    $client->shouldReceive('planWorkflow')
        ->once()
        ->andReturn([
            'status' => 'success',
            'message' => 'ok',
            'data' => [
                'workflow_id' => 'wf-123',
                'status' => 'pending',
            ],
            'errors' => [],
        ]);
    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $response = $this->postJson('/api/v1/ai/workflows/start', [
        'workflow_type' => 'procurement',
        'rfq_id' => 'RFQ-1',
        'inputs' => [
            'rfq' => ['scope' => 'Demo build'],
            'quotes' => [],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.workflow_id', 'wf-123');

    expect(AiWorkflow::query()->where('workflow_id', 'wf-123')->where('company_id', $company->id)->exists())->toBeTrue();
    expect(AiWorkflowStep::query()->where('workflow_id', 'wf-123')->count())->toBeGreaterThan(0);
});

it('drafts the next workflow step', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotActionUser();

    $workflow = AiWorkflow::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'workflow_id' => 'wf-456',
        'steps_json' => ['steps' => []],
    ]);

    AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflow->workflow_id,
        'step_index' => 0,
        'action_type' => 'rfq_draft',
    ]);

    $client = Mockery::mock(AiClient::class);
    $client->shouldReceive('nextWorkflowStep')
        ->once()
        ->andReturn([
            'status' => 'success',
            'message' => 'ok',
            'data' => [
                'workflow_id' => $workflow->workflow_id,
                'workflow_status' => 'in_progress',
                'step' => [
                    'step_index' => 0,
                    'action_type' => 'rfq_draft',
                    'approval_state' => 'pending',
                    'required_inputs' => ['rfq_id' => 'RFQ-7'],
                    'draft_output' => [
                        'summary' => 'RFQ draft ready',
                        'payload' => ['line_items' => []],
                    ],
                ],
            ],
            'errors' => [],
        ]);
    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $response = $this->getJson('/api/v1/ai/workflows/' . $workflow->workflow_id . '/next');

    $response->assertOk()
        ->assertJsonPath('data.workflow.workflow_id', $workflow->workflow_id)
        ->assertJsonPath('data.step.action_type', 'rfq_draft')
        ->assertJsonPath('data.step.draft.summary', 'RFQ draft ready');
});

it('approves a workflow step', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotActionUser();

    $workflow = AiWorkflow::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'workflow_id' => 'wf-789',
        'steps_json' => ['steps' => []],
        'current_step' => 0,
    ]);

    $step = AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflow->workflow_id,
        'step_index' => 0,
        'action_type' => 'rfq_draft',
        'draft_json' => [
            'summary' => 'RFQ ready',
            'payload' => ['line_items' => []],
        ],
    ]);

    $client = Mockery::mock(AiClient::class);
    $client->shouldReceive('completeWorkflowStep')
        ->once()
        ->andReturn([
            'status' => 'success',
            'message' => 'ok',
            'data' => [
                'workflow_status' => 'completed',
                'next_step' => null,
            ],
            'errors' => [],
        ]);
    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $response = $this->postJson('/api/v1/ai/workflows/' . $workflow->workflow_id . '/complete', [
        'step_index' => $step->step_index,
        'approval' => true,
        'output' => ['notes' => 'Approved'],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.step.approval_status', 'approved')
        ->assertJsonPath('data.workflow.status', 'completed');

    expect($step->fresh()->isApproved())->toBeTrue();
});

it('forbids drafting workflow steps without workflow access permissions', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotActionUser('buyer_requester');

    $workflow = AiWorkflow::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'workflow_id' => 'wf-unauthorized',
        'steps_json' => ['steps' => []],
    ]);

    AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflow->workflow_id,
        'step_index' => 0,
        'action_type' => 'rfq_draft',
    ]);

    actingAs($user);

    $response = $this->getJson('/api/v1/ai/workflows/' . $workflow->workflow_id . '/next');

    $response->assertForbidden()
        ->assertJsonPath('errors.code', 'workflow_forbidden');
});

it('forbids approving workflow steps when user lacks approval permissions', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotActionUser('buyer_requester');

    $workflow = AiWorkflow::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'workflow_id' => 'wf-no-approval',
        'steps_json' => ['steps' => []],
        'current_step' => 0,
    ]);

    $step = AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflow->workflow_id,
        'step_index' => 0,
        'action_type' => 'compare_quotes',
        'draft_json' => [
            'summary' => 'Quote draft',
            'payload' => ['rankings' => []],
        ],
    ]);

    actingAs($user);

    $response = $this->postJson('/api/v1/ai/workflows/' . $workflow->workflow_id . '/complete', [
        'step_index' => $step->step_index,
        'approval' => true,
        'output' => ['selected_supplier' => 'SUP-1'],
    ]);

    $response->assertForbidden()
        ->assertJsonPath('errors.code', 'workflow_approval_forbidden');
});

it('rejects workflow requests when plan disables ai workflows', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotActionUser();

    $company->plan?->update(['approvals_enabled' => false]);

    actingAs($user);

    $response = $this->postJson('/api/v1/ai/workflows/start', [
        'workflow_type' => 'procurement',
        'rfq_id' => 'RFQ-PLAN',
        'inputs' => [
            'rfq' => ['scope' => 'Gate check'],
        ],
    ]);

    $response->assertStatus(Response::HTTP_PAYMENT_REQUIRED)
        ->assertJsonPath('errors.code', 'ai_workflows_disabled');
});

it('allows ai workflows when company feature flag overrides entitlement', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotActionUser();

    $company->plan?->update(['approvals_enabled' => false]);

    CompanyFeatureFlag::query()->create([
        'company_id' => $company->id,
        'key' => 'ai_workflows_enabled',
        'value' => ['enabled' => true],
    ]);

    $client = Mockery::mock(AiClient::class);
    $client->shouldReceive('planWorkflow')
        ->once()
        ->andReturn([
            'status' => 'success',
            'message' => 'ok',
            'data' => [
                'workflow_id' => 'wf-override',
                'status' => 'pending',
            ],
            'errors' => [],
        ]);
    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $response = $this->postJson('/api/v1/ai/workflows/start', [
        'workflow_type' => 'procurement',
        'rfq_id' => 'RFQ-OVERRIDE',
        'inputs' => [
            'rfq' => ['scope' => 'Override gate'],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.workflow_id', 'wf-override');
});

it('lists workflow audit events for a workflow', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotActionUser();

    $workflow = AiWorkflow::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'workflow_id' => 'wf-audit-log',
        'workflow_type' => 'procurement',
        'steps_json' => ['steps' => []],
    ]);

    $readyEvent = AiEvent::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'feature' => 'workflow_step_ready',
        'request_json' => [
            'event' => 'workflow_step_ready',
            'workflow' => [
                'workflow_id' => $workflow->workflow_id,
                'workflow_type' => $workflow->workflow_type,
                'status' => 'in_progress',
                'current_step' => 0,
                'step_index' => 0,
                'action_type' => 'rfq_draft',
            ],
            'payload' => ['notes' => 'Drafted'],
        ],
        'status' => 'success',
    ]);

    $readyEvent->update([
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    AiEvent::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'feature' => 'workflow_step_approved',
        'request_json' => [
            'event' => 'workflow_step_approved',
            'workflow' => [
                'workflow_id' => $workflow->workflow_id,
                'workflow_type' => $workflow->workflow_type,
                'status' => 'completed',
                'current_step' => 1,
                'step_index' => 0,
                'action_type' => 'rfq_draft',
            ],
            'payload' => ['notes' => 'Approved RFQ draft'],
        ],
        'status' => 'success',
    ]);

    actingAs($user);

    $response = $this->getJson('/api/v1/ai/workflows/' . $workflow->workflow_id . '/events');

    $response->assertOk()
        ->assertJsonPath('data.items.0.event', 'workflow_step_approved')
        ->assertJsonPath('data.items.0.workflow.workflow_id', $workflow->workflow_id)
        ->assertJsonPath('data.items.1.payload.notes', 'Drafted');
});

it('forbids viewing workflow audit events without workflow access', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotActionUser('buyer_requester');

    $workflow = AiWorkflow::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'workflow_id' => 'wf-no-events',
        'workflow_type' => 'procurement',
    ]);

    actingAs($user);

    $response = $this->getJson('/api/v1/ai/workflows/' . $workflow->workflow_id . '/events');

    $response->assertForbidden()
        ->assertJsonPath('errors.code', 'workflow_forbidden');
});

it('records ai events for workflow lifecycle actions', function (): void {
    ['user' => $user] = provisionCopilotActionUser();

    $client = Mockery::mock(AiClient::class);
    $client->shouldReceive('planWorkflow')
        ->once()
        ->andReturn([
            'status' => 'success',
            'message' => 'ok',
            'data' => [
                'workflow_id' => 'wf-audit',
                'status' => 'pending',
            ],
            'errors' => [],
        ]);
    $client->shouldReceive('nextWorkflowStep')
        ->once()
        ->andReturn([
            'status' => 'success',
            'message' => 'ok',
            'data' => [
                'workflow_id' => 'wf-audit',
                'workflow_status' => 'in_progress',
                'step' => [
                    'step_index' => 0,
                    'action_type' => 'rfq_draft',
                    'approval_state' => 'pending',
                    'required_inputs' => ['rfq_id' => 'RFQ-100'],
                    'draft_output' => [
                        'summary' => 'RFQ ready',
                        'payload' => ['line_items' => []],
                    ],
                ],
            ],
            'errors' => [],
        ]);
    $client->shouldReceive('completeWorkflowStep')
        ->once()
        ->andReturn([
            'status' => 'success',
            'message' => 'ok',
            'data' => [
                'workflow_status' => 'completed',
                'next_step' => null,
            ],
            'errors' => [],
        ]);

    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $startResponse = $this->postJson('/api/v1/ai/workflows/start', [
        'workflow_type' => 'procurement',
        'rfq_id' => 'RFQ-100',
        'inputs' => [
            'rfq' => ['scope' => 'Audit example'],
        ],
    ])->assertOk();

    $workflowId = $startResponse->json('data.workflow_id');

    $this->getJson('/api/v1/ai/workflows/' . $workflowId . '/next')->assertOk();

    $step = AiWorkflowStep::query()
        ->where('workflow_id', $workflowId)
        ->where('step_index', 0)
        ->firstOrFail();

    $this->postJson('/api/v1/ai/workflows/' . $workflowId . '/complete', [
        'step_index' => $step->step_index,
        'approval' => true,
        'output' => ['notes' => 'Looks good'],
    ])->assertOk();

    expect(AiEvent::query()->where('feature', 'workflow_start')->exists())->toBeTrue();
    expect(AiEvent::query()->where('feature', 'workflow_step_ready')->exists())->toBeTrue();
    expect(AiEvent::query()->where('feature', 'workflow_step_approved')->exists())->toBeTrue();
    expect(AiEvent::query()->where('feature', 'workflow_completed')->exists())->toBeTrue();
});
