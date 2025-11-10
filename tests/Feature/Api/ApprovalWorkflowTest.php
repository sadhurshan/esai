<?php

use App\Enums\ApprovalStatus;
use App\Models\Approval;
use App\Models\ApprovalRule;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Delegation;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

function prepareApprovalsEnvironment(array $planOverrides = []): array
{
    $code = 'appr-'.Str::lower(Str::random(8));

    $plan = Plan::factory()->create(array_merge([
        'code' => $code,
        'approvals_enabled' => true,
        'approval_levels_limit' => 5,
    ], $planOverrides));

    $company = Company::factory()->create([
        'plan_code' => $plan->code,
        'status' => 'active',
        'registration_no' => 'REG-1000',
        'tax_id' => 'TAX-1000',
    ]);

    $user = User::factory()->create([
        'role' => 'buyer_admin',
        'company_id' => $company->id,
        'email' => 'buyer-admin@example.com',
    ]);

    $company->owner_user_id = $user->id;
    $company->save();

    return [$plan, $company, $user];
}

it('stores and updates approval rules enforcing level limits', function (): void {
    [$plan, $company, $user] = prepareApprovalsEnvironment(['approval_levels_limit' => 3]);

    actingAs($user);

    $payload = [
        'target_type' => 'purchase_order',
        'threshold_min' => 0,
        'threshold_max' => 10000,
        'levels_json' => [
            ['level_no' => 1, 'approver_role' => 'buyer_admin'],
            ['level_no' => 2, 'approver_user_id' => $user->id],
            ['level_no' => 3, 'approver_role' => 'owner'],
        ],
    ];

    $response = postJson('/api/approvals/rules', $payload);
    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.levels.0.level_no', 1)
        ->assertJsonPath('data.levels.2.level_no', 3);

    $rule = ApprovalRule::find($response->json('data.id'));

    expect($rule)->not->toBeNull();
    expect($rule->levelsCount())->toBe(3);

    $update = $payload;
    $update['threshold_max'] = 50000;
    $update['levels_json'][2]['approver_user_id'] = $user->id;
    unset($update['levels_json'][2]['approver_role']);

    putJson("/api/approvals/rules/{$rule->id}", $update)
        ->assertOk()
        ->assertJsonPath('data.threshold_max', 50000);

    postJson('/api/approvals/rules', array_merge($payload, [
        'levels_json' => [
            ['level_no' => 1, 'approver_role' => 'buyer_admin'],
            ['level_no' => 2, 'approver_role' => 'owner'],
            ['level_no' => 3, 'approver_role' => 'finance'],
            ['level_no' => 4, 'approver_role' => 'finance'],
        ],
    ]))->assertStatus(402);
});

it('blocks approvals for starter plan and allows growth plan', function (): void {
    [$plan, $company, $user] = prepareApprovalsEnvironment([
        'approvals_enabled' => false,
        'approval_levels_limit' => 0,
    ]);

    actingAs($user);

    postJson('/api/approvals/rules', [
        'target_type' => 'purchase_order',
        'threshold_min' => 0,
        'levels_json' => [
            ['level_no' => 1, 'approver_user_id' => $user->id],
        ],
    ])->assertStatus(402);

    $plan->approvals_enabled = true;
    $plan->approval_levels_limit = 5;
    $plan->save();

    $user = $user->fresh();
    actingAs($user);

    postJson('/api/approvals/rules', [
        'target_type' => 'purchase_order',
        'threshold_min' => 0,
        'levels_json' => [
            ['level_no' => 1, 'approver_user_id' => $user->id],
        ],
    ])->assertCreated();
});

it('triggers approvals when purchase order exceeds threshold', function (): void {
    [$plan, $company, $user] = prepareApprovalsEnvironment();

    $rule = ApprovalRule::factory()->create([
        'company_id' => $company->id,
        'target_type' => 'purchase_order',
        'threshold_min' => 1000,
        'threshold_max' => null,
        'levels_json' => [
            ['level_no' => 1, 'approver_user_id' => $user->id],
        ],
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'draft',
    ]);

    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'quantity' => 10,
        'unit_price' => 200,
    ]);

    Mail::fake();

    /** @var ApprovalWorkflowService $workflow */
    $workflow = app(ApprovalWorkflowService::class);
    $approval = $workflow->triggerApproval($purchaseOrder);

    expect($approval)->not->toBeNull();
    expect(Approval::query()->where('target_id', $purchaseOrder->id)->count())->toBe(1);
});

it('advances approvals across levels and confirms the purchase order', function (): void {
    [$plan, $company, $user] = prepareApprovalsEnvironment();

    $secondApprover = User::factory()->create([
        'role' => 'finance',
        'company_id' => $company->id,
    ]);

    $rule = ApprovalRule::factory()->create([
        'company_id' => $company->id,
        'target_type' => 'purchase_order',
        'levels_json' => [
            ['level_no' => 1, 'approver_user_id' => $user->id],
            ['level_no' => 2, 'approver_user_id' => $secondApprover->id],
        ],
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'draft',
    ]);

    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'quantity' => 4,
        'unit_price' => 400,
    ]);

    Mail::fake();

    $workflow = app(ApprovalWorkflowService::class);
    $approval = $workflow->triggerApproval($purchaseOrder);

    actingAs($user);
    postJson("/api/approvals/requests/{$approval->id}/action", [
        'decision' => 'approve',
    ])->assertOk();

    $next = Approval::query()->where('target_id', $purchaseOrder->id)->where('status', 'pending')->first();
    expect($next)->not->toBeNull();
    expect($next->level_no)->toBe(2);

    actingAs($secondApprover);
    postJson("/api/approvals/requests/{$next->id}/action", [
        'decision' => 'approve',
    ])->assertOk();

    expect($purchaseOrder->fresh()->status)->toBe('confirmed');
});

it('marks purchase order as cancelled when rejected and closes pending approvals', function (): void {
    [$plan, $company, $user] = prepareApprovalsEnvironment();

    $secondApprover = User::factory()->create([
        'role' => 'finance',
        'company_id' => $company->id,
    ]);

    $rule = ApprovalRule::factory()->create([
        'company_id' => $company->id,
        'target_type' => 'purchase_order',
        'levels_json' => [
            ['level_no' => 1, 'approver_user_id' => $user->id],
            ['level_no' => 2, 'approver_user_id' => $secondApprover->id],
        ],
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'draft',
    ]);

    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'quantity' => 5,
        'unit_price' => 300,
    ]);

    Mail::fake();

    $workflow = app(ApprovalWorkflowService::class);
    $approval = $workflow->triggerApproval($purchaseOrder);

    actingAs($user);
    postJson("/api/approvals/requests/{$approval->id}/action", [
        'decision' => 'reject',
        'comment' => 'Budget exceeded.',
    ])->assertOk();

    expect($purchaseOrder->fresh()->status)->toBe('cancelled');
    expect(Approval::query()->where('target_id', $purchaseOrder->id)->where('status', 'pending')->count())->toBe(0);
});

it('allows delegated approver to act within date range', function (): void {
    [$plan, $company, $user] = prepareApprovalsEnvironment();

    $delegate = User::factory()->create([
        'role' => 'buyer_admin',
        'company_id' => $company->id,
        'email' => 'delegate@example.com',
    ]);

    $rule = ApprovalRule::factory()->create([
        'company_id' => $company->id,
        'target_type' => 'purchase_order',
        'levels_json' => [
            ['level_no' => 1, 'approver_user_id' => $user->id],
        ],
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'draft',
    ]);

    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'quantity' => 2,
        'unit_price' => 700,
    ]);

    Mail::fake();

    $workflow = app(ApprovalWorkflowService::class);
    $approval = $workflow->triggerApproval($purchaseOrder);

    Delegation::factory()->create([
        'company_id' => $company->id,
        'approver_user_id' => $user->id,
        'delegate_user_id' => $delegate->id,
        'starts_at' => Carbon::today()->subDay(),
        'ends_at' => Carbon::today()->addDay(),
        'created_by' => $user->id,
    ]);

    actingAs($delegate);
    postJson("/api/approvals/requests/{$approval->id}/action", [
        'decision' => 'approve',
    ])->assertOk();

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Approved);
});

it('records audit logs and sends notifications on approval actions', function (): void {
    [$plan, $company, $user] = prepareApprovalsEnvironment();

    $rule = ApprovalRule::factory()->create([
        'company_id' => $company->id,
        'target_type' => 'purchase_order',
        'levels_json' => [
            ['level_no' => 1, 'approver_user_id' => $user->id],
        ],
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'draft',
    ]);

    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'quantity' => 3,
        'unit_price' => 600,
    ]);

    Mail::fake();

    $workflow = app(ApprovalWorkflowService::class);
    $approval = $workflow->triggerApproval($purchaseOrder);

    expect(AuditLog::query()->where('entity_type', Approval::class)->count())->toBeGreaterThan(0);
    expect(Notification::query()
        ->where('entity_type', PurchaseOrder::class)
        ->where('entity_id', $purchaseOrder->id)
        ->count())->toBeGreaterThan(0);

    actingAs($user);
    postJson("/api/approvals/requests/{$approval->id}/action", [
        'decision' => 'approve',
    ])->assertOk();

    expect(AuditLog::query()->where('entity_type', Approval::class)->count())->toBeGreaterThan(1);
});
