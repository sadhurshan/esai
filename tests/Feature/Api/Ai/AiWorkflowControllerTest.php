<?php

require_once __DIR__ . '/helpers.php';

use App\Models\AiEvent;
use App\Models\AiWorkflow;
use App\Models\AiWorkflowStep;
use App\Models\CompanyFeatureFlag;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RFQ;
use App\Models\RfqItem;
use App\Models\RfqItemAward;
use App\Models\RoleTemplate;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Support\Permissions\PermissionRegistry;
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

    $steps = AiWorkflowStep::query()
        ->where('workflow_id', 'wf-123')
        ->orderBy('step_index')
        ->pluck('action_type')
        ->all();

    expect($steps)->toEqual([
        'rfq_draft',
        'compare_quotes',
        'award_quote',
        'po_draft',
    ]);
});

it('queues extended steps for the procurement_full_flow template', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotActionUser();

    $client = Mockery::mock(AiClient::class);
    $client->shouldReceive('planWorkflow')
        ->once()
        ->andReturn([
            'status' => 'success',
            'message' => 'ok',
            'data' => [
                'workflow_id' => 'wf-full',
                'status' => 'pending',
            ],
            'errors' => [],
        ]);
    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $response = $this->postJson('/api/v1/ai/workflows/start', [
        'workflow_type' => 'procurement_full_flow',
        'rfq_id' => 'RFQ-9',
        'inputs' => [
            'rfq' => ['scope' => 'Extended flow'],
            'quotes' => [],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.workflow_id', 'wf-full');

    $steps = AiWorkflowStep::query()
        ->where('workflow_id', 'wf-full')
        ->orderBy('step_index')
        ->pluck('action_type')
        ->all();

    expect($steps)->toEqual([
        'rfq_draft',
        'compare_quotes',
        'award_quote',
        'po_draft',
        'receiving_quality',
        'invoice_approval',
        'payment_process',
    ]);
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

it('creates awards when the award quote step is approved', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotActionUser();

    $supplier = Supplier::factory()->for($company)->create();

    $rfq = RFQ::factory()->for($company)->create([
        'created_by' => $user->id,
        'status' => RFQ::STATUS_OPEN,
        'due_at' => now()->addDays(10),
    ]);

    $items = RfqItem::factory()->count(2)->create([
        'rfq_id' => $rfq->id,
        'company_id' => $company->id,
        'created_by' => $user->id,
        'qty' => 5,
    ]);

    $quote = Quote::factory()->create([
        'company_id' => $company->id,
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'submitted_by' => $user->id,
        'status' => 'submitted',
    ]);

    foreach ($items as $index => $item) {
        QuoteItem::query()->create([
            'company_id' => $company->id,
            'quote_id' => $quote->id,
            'rfq_item_id' => $item->id,
            'unit_price' => 100 + $index,
            'currency' => 'USD',
            'unit_price_minor' => 10000 + $index,
            'lead_time_days' => 10,
            'status' => 'pending',
        ]);
    }

    $workflow = AiWorkflow::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'workflow_id' => 'wf-award-quote',
        'steps_json' => ['steps' => []],
        'current_step' => 1,
        'workflow_type' => 'procurement',
    ]);

    $awardPayload = [
        'rfq_id' => (string) $rfq->id,
        'supplier_id' => (string) $supplier->id,
        'selected_quote_id' => (string) $quote->id,
        'justification' => 'Best overall value.',
        'delivery_date' => now()->addDays(21)->toDateString(),
        'terms' => ['Net 30', 'Maintain quoted pricing'],
    ];

    $step = AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflow->workflow_id,
        'step_index' => 1,
        'action_type' => 'award_quote',
        'draft_json' => [
            'summary' => 'Award quote draft ready',
            'payload' => $awardPayload,
        ],
        'input_json' => ['rfq_id' => $rfq->id],
    ]);

    $client = Mockery::mock(AiClient::class);
    $client->shouldReceive('completeWorkflowStep')
        ->once()
        ->andReturn([
            'status' => 'success',
            'message' => 'ok',
            'data' => [
                'workflow_status' => 'in_progress',
                'next_step' => null,
            ],
            'errors' => [],
        ]);
    $this->app->instance(AiClient::class, $client);

    actingAs($user);

    $response = $this->postJson('/api/v1/ai/workflows/' . $workflow->workflow_id . '/complete', [
        'step_index' => $step->step_index,
        'approval' => true,
        'output' => ['payload' => $awardPayload],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.step.approval_status', 'approved');

    $awards = RfqItemAward::query()->where('rfq_id', $rfq->id)->get();

    expect($awards)->toHaveCount($items->count());

    $firstAward = $awards->first();
    expect($firstAward?->quote_id)->toBe($quote->id);
    expect($firstAward?->supplier_id)->toBe($supplier->id);
    expect($firstAward?->awarded_by)->toBe($user->id);

    expect($quote->fresh()->status)->toBe('awarded');
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

it('requires quote permissions to approve award quote steps', function (): void {
    ['user' => $user, 'company' => $company] = provisionCopilotActionUser('finance');

    RoleTemplate::query()->create([
        'slug' => 'finance',
        'name' => 'Finance',
        'description' => 'Finance override for tests',
        'permissions' => ['rfqs.write'],
    ]);

    app(PermissionRegistry::class)->forgetRoleCache('finance');

    $workflow = AiWorkflow::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'workflow_id' => 'wf-award-permissions',
        'workflow_type' => 'procurement',
        'steps_json' => ['steps' => []],
        'current_step' => 1,
    ]);

    $awardPayload = [
        'rfq_id' => 'RFQ-100',
        'supplier_id' => 'SUP-77',
        'selected_quote_id' => 'Q-55',
        'justification' => 'Best TCO',
        'delivery_date' => now()->addDays(15)->toDateString(),
        'terms' => ['Net 30'],
    ];

    $step = AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflow->workflow_id,
        'step_index' => 1,
        'action_type' => 'award_quote',
        'draft_json' => [
            'summary' => 'Award quote draft',
            'payload' => $awardPayload,
        ],
        'input_json' => ['rfq_id' => 'RFQ-100'],
    ]);

    actingAs($user);

    $response = $this->postJson('/api/v1/ai/workflows/' . $workflow->workflow_id . '/complete', [
        'step_index' => $step->step_index,
        'approval' => true,
        'output' => ['payload' => $awardPayload],
    ]);

    $response->assertForbidden()
        ->assertJsonPath('message', 'You are not authorized to resolve this step.');

    expect($step->fresh()->isPending())->toBeTrue();
});

it('escalates high value purchase orders to finance approvers', function (): void {
    ['user' => $approver, 'company' => $company] = provisionCopilotActionUser();

    RoleTemplate::query()->create([
        'slug' => 'workflow_tester',
        'name' => 'Workflow Tester',
        'description' => 'Limited workflow approver for tests',
        'permissions' => ['ai.workflows.run', 'ai.workflows.approve', 'orders.write', 'rfqs.write'],
    ]);

    RoleTemplate::query()->create([
        'slug' => 'workflow_finance',
        'name' => 'Workflow Finance Approver',
        'description' => 'Finance approver for workflows',
        'permissions' => ['ai.workflows.run', 'ai.workflows.approve', 'orders.write', 'finance.write', 'rfqs.write'],
    ]);

    app(PermissionRegistry::class)->forgetRoleCache('workflow_tester');
    app(PermissionRegistry::class)->forgetRoleCache('workflow_finance');

    $approver->forceFill(['role' => 'workflow_tester'])->save();
    $financeUser = User::factory()->for($company)->create(['role' => 'workflow_finance']);

    $workflow = AiWorkflow::factory()->create([
        'company_id' => $company->id,
        'user_id' => $approver->id,
        'workflow_id' => 'wf-high-value',
        'workflow_type' => 'procurement',
        'steps_json' => ['steps' => []],
        'current_step' => 3,
    ]);

    $step = AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflow->workflow_id,
        'step_index' => 3,
        'action_type' => 'po_draft',
        'draft_json' => [
            'summary' => 'High value PO draft',
            'payload' => [
                'total_value' => 125000,
                'supplier' => [
                    'supplier_id' => 'SUP-900',
                    'name' => 'Acme Industries',
                ],
            ],
        ],
        'input_json' => ['rfq_id' => 'RFQ-500'],
    ]);

    $requirements = app(\App\Services\Ai\WorkflowService::class)->resolveRequiredApprovals($step->fresh());
    expect($requirements['permissions'] ?? [])->toContain('finance.write');
    $testerPermissions = app(PermissionRegistry::class)->permissionsForRole('workflow_tester');
    expect($testerPermissions)->toContain('orders.write');
    expect($testerPermissions)->not->toContain('finance.write');
    expect(app(PermissionRegistry::class)->userHasAll($approver->fresh(), $requirements['permissions'], $company->id))->toBeFalse();
    expect(app(PermissionRegistry::class)->userHasAll($approver->fresh(), ['finance.write'], $company->id))->toBeFalse();

    $client = Mockery::mock(AiClient::class);
    $client->shouldReceive('completeWorkflowStep')
        ->once()
        ->andReturn([
            'status' => 'success',
            'message' => 'ok',
            'data' => [
                'workflow_status' => 'in_progress',
                'next_step' => null,
            ],
            'errors' => [],
        ]);
    $this->app->instance(AiClient::class, $client);

    actingAs($approver);

    $this->postJson('/api/v1/ai/workflows/' . $workflow->workflow_id . '/complete', [
        'step_index' => $step->step_index,
        'approval' => true,
        'output' => ['notes' => 'Looks fine'],
    ])->assertForbidden()
        ->assertJsonPath('errors.code', 'workflow_approval_forbidden');

    expect($step->fresh()->isPending())->toBeTrue();

    actingAs($financeUser);

    $response = $this->postJson('/api/v1/ai/workflows/' . $workflow->workflow_id . '/complete', [
        'step_index' => $step->step_index,
        'approval' => true,
        'output' => ['notes' => 'Finance review complete'],
    ])->assertOk();

    expect($response->json('data.step.approval_requirements.permissions'))->toContain('finance.write');
    expect($step->fresh()->approved_by)->toBe($financeUser->id);
});

it('requires supplier approvers for high risk awards', function (): void {
    ['user' => $quoteApprover, 'company' => $company] = provisionCopilotActionUser();

    RoleTemplate::query()->create([
        'slug' => 'quote_approver',
        'name' => 'Quote Approver',
        'description' => 'Quote approver without supplier rights',
        'permissions' => ['ai.workflows.run', 'ai.workflows.approve', 'quotes.write', 'rfqs.write'],
    ]);

    RoleTemplate::query()->create([
        'slug' => 'supplier_manager',
        'name' => 'Supplier Manager',
        'description' => 'Supplier approver for workflows',
        'permissions' => ['ai.workflows.run', 'ai.workflows.approve', 'quotes.write', 'suppliers.write', 'rfqs.write'],
    ]);

    app(PermissionRegistry::class)->forgetRoleCache('quote_approver');
    app(PermissionRegistry::class)->forgetRoleCache('supplier_manager');

    $quoteApprover->forceFill(['role' => 'quote_approver'])->save();
    $supplierLead = User::factory()->for($company)->create(['role' => 'supplier_manager']);

    $workflow = AiWorkflow::factory()->create([
        'company_id' => $company->id,
        'user_id' => $quoteApprover->id,
        'workflow_id' => 'wf-supplier-risk',
        'workflow_type' => 'procurement',
        'steps_json' => ['steps' => []],
        'current_step' => 2,
    ]);

    $step = AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflow->workflow_id,
        'step_index' => 2,
        'action_type' => 'award_quote',
        'draft_json' => [
            'summary' => 'Award recommendation',
            'payload' => [
                'rfq_id' => 'RFQ-700',
                'supplier' => [
                    'supplier_id' => 'SUP-44',
                    'name' => 'Nova Components',
                    'risk_grade' => 'high',
                    'risk_score' => 0.35,
                ],
            ],
        ],
        'input_json' => ['rfq_id' => 'RFQ-700'],
    ]);

    $supplierRequirements = app(\App\Services\Ai\WorkflowService::class)->resolveRequiredApprovals($step->fresh());
    expect($supplierRequirements['permissions'] ?? [])->toContain('suppliers.write');
    $quotePermissions = app(PermissionRegistry::class)->permissionsForRole('quote_approver');
    expect($quotePermissions)->toContain('quotes.write');
    expect($quotePermissions)->not->toContain('suppliers.write');
    expect(app(PermissionRegistry::class)->userHasAll($quoteApprover->fresh(), $supplierRequirements['permissions'], $company->id))->toBeFalse();
    expect(app(PermissionRegistry::class)->userHasAll($quoteApprover->fresh(), ['suppliers.write'], $company->id))->toBeFalse();

    $client = Mockery::mock(AiClient::class);
    $client->shouldReceive('completeWorkflowStep')
        ->once()
        ->andReturn([
            'status' => 'success',
            'message' => 'ok',
            'data' => [
                'workflow_status' => 'in_progress',
                'next_step' => null,
            ],
            'errors' => [],
        ]);
    $this->app->instance(AiClient::class, $client);

    actingAs($quoteApprover);

    $this->postJson('/api/v1/ai/workflows/' . $workflow->workflow_id . '/complete', [
        'step_index' => $step->step_index,
        'approval' => true,
        'output' => ['notes' => 'Looks good'],
    ])->assertForbidden()
        ->assertJsonPath('errors.code', 'workflow_approval_forbidden');

    actingAs($supplierLead);

    $response = $this->postJson('/api/v1/ai/workflows/' . $workflow->workflow_id . '/complete', [
        'step_index' => $step->step_index,
        'approval' => true,
        'output' => ['notes' => 'Supplier risk approved'],
    ])->assertOk();

    expect($response->json('data.step.approval_requirements.permissions'))->toContain('suppliers.write');
    expect($step->fresh()->approved_by)->toBe($supplierLead->id);
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
