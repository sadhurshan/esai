<?php

use App\Enums\CompanyStatus;
use App\Enums\SupplierApplicationStatus;
use App\Models\AiWorkflow;
use App\Models\AiWorkflowStep;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Models\SupplierApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('returns analytics overview for platform super admins', function (): void {
    $user = User::factory()->create();
    PlatformAdmin::factory()->super()->for($user)->create();

    $activeCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'storage_used_mb' => 512,
        'rfqs_monthly_used' => 12,
    ]);

    Company::factory()->create(['status' => CompanyStatus::Pending->value]);
    Company::factory()->create(['status' => CompanyStatus::Trial->value]);
    Company::factory()->create(['status' => CompanyStatus::Suspended->value]);

    $recentRfq = RFQ::factory()->for($activeCompany)->create([
        'created_at' => now()->subDays(1),
    ]);

    RFQ::factory()->for($activeCompany)->create([
        'created_at' => now()->subMonths(1)->startOfMonth()->addDays(2),
    ]);

    $supplier = Supplier::factory()->create(['company_id' => $activeCompany->id]);
    $quoteSubmitter = User::factory()->create(['company_id' => $activeCompany->id]);

    Quote::query()->create([
        'company_id' => $activeCompany->id,
        'rfq_id' => $recentRfq->id,
        'supplier_id' => $supplier->id,
        'submitted_by' => $quoteSubmitter->id,
        'currency' => 'USD',
        'unit_price' => 100,
        'min_order_qty' => 1,
        'lead_time_days' => 10,
        'note' => null,
        'status' => 'submitted',
        'revision_no' => 1,
        'subtotal' => 100,
        'tax_amount' => 0,
        'total' => 100,
        'subtotal_minor' => 10000,
        'tax_amount_minor' => 0,
        'total_minor' => 10000,
        'submitted_at' => now(),
    ]);

    PurchaseOrder::factory()->create([
        'company_id' => $activeCompany->id,
        'created_at' => now()->subDays(2),
    ]);

    SupplierApplication::factory()->create([
        'company_id' => $activeCompany->id,
        'status' => SupplierApplicationStatus::Pending,
    ]);

    AuditLog::query()->create([
        'company_id' => $activeCompany->id,
        'user_id' => $user->id,
        'entity_type' => 'company',
        'entity_id' => (string) $activeCompany->id,
        'action' => 'created',
        'before' => null,
        'after' => ['status' => CompanyStatus::Active->value],
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test-suite',
    ]);

    $completedWorkflow = AiWorkflow::factory()->create([
        'company_id' => $activeCompany->id,
        'user_id' => $user->id,
        'workflow_type' => 'rfq_draft',
        'status' => AiWorkflow::STATUS_COMPLETED,
        'current_step' => 1,
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDay(),
        'last_event_time' => now()->subDay(),
        'steps_json' => [
            'steps' => [
                ['step_index' => 0, 'name' => 'RFQ draft'],
                ['step_index' => 1, 'name' => 'Compare quotes'],
            ],
        ],
    ]);

    AiWorkflowStep::factory()->create([
        'company_id' => $activeCompany->id,
        'workflow_id' => $completedWorkflow->workflow_id,
        'step_index' => 1,
        'approval_status' => AiWorkflowStep::APPROVAL_APPROVED,
        'created_at' => now()->subHours(3),
        'approved_at' => now()->subHour(),
    ]);

    $failedWorkflow = AiWorkflow::factory()->create([
        'company_id' => $activeCompany->id,
        'user_id' => $user->id,
        'workflow_type' => 'po_draft',
        'status' => AiWorkflow::STATUS_FAILED,
        'current_step' => 2,
        'created_at' => now()->subHours(5),
        'updated_at' => now()->subMinutes(30),
        'last_event_type' => 'workflow_failed',
        'last_event_time' => now()->subMinutes(30),
        'steps_json' => [
            'steps' => [
                ['step_index' => 2, 'name' => 'PO draft'],
            ],
        ],
    ]);

    actingAs($user);

    $response = getJson('/api/admin/analytics/overview');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'tenants' => ['total', 'active', 'trialing', 'suspended', 'pending'],
                'subscriptions' => ['active', 'trialing', 'past_due'],
                'usage' => [
                    'rfqs_month_to_date',
                    'rfqs_last_month',
                    'rfqs_capacity_used',
                    'quotes_month_to_date',
                    'purchase_orders_month_to_date',
                    'storage_used_mb',
                    'avg_storage_used_mb',
                ],
                'people' => ['users_total', 'active_last_7_days', 'listed_suppliers'],
                'approvals' => ['pending_companies', 'pending_supplier_applications'],
                'workflows' => [
                    'window_days',
                    'total_started',
                    'completed',
                    'in_progress',
                    'failed',
                    'completion_rate',
                    'avg_step_approval_minutes',
                    'failed_alerts',
                ],
                'trends' => ['rfqs', 'tenants'],
                'recent' => ['companies', 'audit_logs'],
            ],
        ]);

    $response->assertJsonPath('data.workflows.total_started', 2);
    $response->assertJsonPath('data.workflows.completed', 1);
    $response->assertJsonPath('data.workflows.failed', 1);
    $response->assertJsonPath('data.workflows.completion_rate', 50);
    $response->assertJsonPath('data.workflows.avg_step_approval_minutes', 120);
    $response->assertJsonPath('data.workflows.failed_alerts.0.workflow_id', $failedWorkflow->workflow_id);
});

it('requires admin guard for analytics overview', function (): void {
    getJson('/api/admin/analytics/overview')->assertUnauthorized();

    $user = User::factory()->create();
    actingAs($user);

    getJson('/api/admin/analytics/overview')->assertForbidden();
});
