<?php

use App\Enums\CreditNoteStatus;
use App\Models\Company;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

function prepareCreditNotePlanContext(array $planOverrides = [], array $companyOverrides = [], array $userOverrides = []): array
{
    $plan = Plan::factory()->create(array_merge([
        'code' => 'credit-plan-'.str()->uuid()->toString(),
        'credit_notes_enabled' => true,
    ], $planOverrides));

    $company = Company::factory()->create(array_merge([
        'plan_code' => $plan->code,
        'status' => 'active',
    ], $companyOverrides));

    $user = User::factory()->create(array_merge([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ], $userOverrides));

    Subscription::factory()->for($company)->create([
        'stripe_status' => 'active',
    ]);

    return [$plan, $company, $user];
}

beforeEach(function (): void {
    Config::set('documents.disk', 's3');
    Storage::fake('s3');
});

it('creates and issues a credit note with attachments and notifications', function (): void {
    [$plan, $company, $user] = prepareCreditNotePlanContext();

    /** @var PurchaseOrder $purchaseOrder */
    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'confirmed',
    ]);

    /** @var Supplier $supplier */
    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
    ]);

    User::factory()->create([
        'company_id' => $company->id,
        'role' => 'finance',
    ]);

    /** @var Invoice $invoice */
    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'supplier_id' => $supplier->id,
        'total' => 1200.00,
    ]);

    actingAs($user);

    $payload = [
        'reason' => 'Damaged goods credit',
        'amount' => 300.00,
        'attachments' => [
            UploadedFile::fake()->create('credit-note.pdf', 120, 'application/pdf'),
        ],
    ];

    $response = postJson("/api/credit-notes/invoices/{$invoice->id}", $payload);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', CreditNoteStatus::Draft->value)
        ->assertJsonPath('data.amount', '300.00');

    $creditId = $response->json('data.id');
    expect($creditId)->not->toBeNull();

    $creditNote = CreditNote::findOrFail($creditId);
    expect($creditNote->documents()->count())->toBe(1);

    postJson("/api/credit-notes/{$creditNote->id}/issue")
        ->assertOk()
        ->assertJsonPath('data.status', CreditNoteStatus::Issued->value);

    expect(Notification::query()
        ->where('company_id', $company->id)
        ->where('event_type', 'invoice_status_changed')
        ->whereJsonContains('meta->credit_event', 'credit_note.issued')
        ->exists())->toBeTrue();
});

it('approves a credit note, applies amount, and records usage', function (): void {
    [$plan, $company, $issuer] = prepareCreditNotePlanContext();

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'confirmed',
    ]);

    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'supplier_id' => $supplier->id,
        'total' => 900.00,
    ]);

    actingAs($issuer);

    $createResponse = postJson("/api/credit-notes/invoices/{$invoice->id}", [
        'reason' => 'Partial refund for RMA',
        'amount' => 200.00,
    ])->assertCreated();

    $creditId = $createResponse->json('data.id');

    postJson("/api/credit-notes/{$creditId}/issue")->assertOk();

    $approver = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'finance',
    ]);

    actingAs($approver);

    postJson("/api/credit-notes/{$creditId}/approve", [
        'decision' => 'approve',
        'comment' => 'Approved for settlement',
    ])->assertOk()
        ->assertJsonPath('data.status', CreditNoteStatus::Applied->value);

    expect((float) Invoice::find($invoice->id)?->total)->toBe(700.0);
    expect($company->fresh()->credit_notes_monthly_used)->toBe(1);

    expect(Notification::query()
        ->where('event_type', 'invoice_status_changed')
        ->where('entity_id', $creditId)
        ->whereJsonContains('meta->credit_event', 'credit_note.approved')
        ->exists())->toBeTrue();
});

it('prevents unauthorized roles from issuing or approving credit notes', function (): void {
    [$plan, $company, $admin] = prepareCreditNotePlanContext();

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'confirmed',
    ]);

    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'supplier_id' => $supplier->id,
    ]);

    actingAs($admin);

    $creditId = postJson("/api/credit-notes/invoices/{$invoice->id}", [
        'reason' => 'Test credit',
        'amount' => 150.00,
    ])->json('data.id');

    $requester = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_requester',
    ]);

    actingAs($requester);

    postJson("/api/credit-notes/{$creditId}/issue")
        ->assertStatus(403)
        ->assertJsonPath('message', 'Insufficient permissions to issue credit notes.');

    postJson("/api/credit-notes/{$creditId}/approve", [
        'decision' => 'approve',
    ])->assertStatus(403)
        ->assertJsonPath('message', 'Insufficient permissions to review credit notes.');
});

it('enforces plan gating for credit notes', function (): void {
    $plan = Plan::factory()->create([
        'code' => 'starter-plan-'.str()->uuid()->toString(),
        'credit_notes_enabled' => false,
    ]);

    $company = Company::factory()->create([
        'plan_code' => $plan->code,
        'status' => 'active',
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    Subscription::factory()->for($company)->create([
        'stripe_status' => 'active',
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'confirmed',
    ]);

    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'supplier_id' => $supplier->id,
    ]);

    actingAs($user);

    postJson("/api/credit-notes/invoices/{$invoice->id}", [
        'reason' => 'Test credit',
        'amount' => 120.00,
    ])->assertStatus(402)
        ->assertJsonPath('message', 'Upgrade required to access credit notes.');
});
