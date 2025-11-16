<?php

use App\Enums\CreditNoteStatus;
use App\Models\Company;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\CreditNote;
use App\Models\CreditNoteLine;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Subscription;
use Database\Seeders\CurrenciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

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
    seed(CurrenciesSeeder::class);
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
        'subtotal' => 900.00,
        'tax_amount' => 300.00,
        'total' => 1200.00,
        'currency' => 'USD',
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
        ->assertJsonPath('data.amount', '300.00')
        ->assertJsonPath('data.amount_minor', 30000)
        ->assertJsonPath('data.currency', 'USD');

    $creditId = $response->json('data.id');
    expect($creditId)->not->toBeNull();

    $creditNote = CreditNote::findOrFail($creditId);
    expect($creditNote->documents()->count())->toBe(1);
    expect($creditNote->amount_minor)->toBe(30000);

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
        'subtotal' => 800.00,
        'tax_amount' => 100.00,
        'total' => 900.00,
        'currency' => 'USD',
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

    $freshInvoice = Invoice::findOrFail($invoice->id);

    expect((float) $freshInvoice->total)->toBe(700.0);
    expect((float) $freshInvoice->tax_amount)->toBe(0.0);
    expect((float) $freshInvoice->subtotal)->toBe(700.0);
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
        'currency' => 'USD',
    ]);

    actingAs($admin);

    $createResponse = postJson("/api/credit-notes/invoices/{$invoice->id}", [
        'reason' => 'Test credit',
        'amount' => 150.00,
    ])->assertCreated();

    $creditId = $createResponse->json('data.id');
    expect($creditId)->not->toBeNull();

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

it('updates credit note lines and recalculates totals', function (): void {
    [$plan, $company, $user] = prepareCreditNotePlanContext();

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
        'subtotal' => 1000.00,
        'tax_amount' => 0,
        'total' => 1000.00,
        'currency' => 'USD',
    ]);

    $lineA = InvoiceLine::factory()->for($invoice)->create([
        'quantity' => 5,
        'unit_price' => 100,
        'unit_price_minor' => 10000,
    ]);

    $lineB = InvoiceLine::factory()->for($invoice)->create([
        'quantity' => 3,
        'unit_price' => 200,
        'unit_price_minor' => 20000,
    ]);

    actingAs($user);

    $creditId = postJson("/api/credit-notes/invoices/{$invoice->id}", [
        'reason' => 'Invoice variance credit',
        'amount' => 100.00,
    ])->json('data.id');

    $response = putJson("/api/credit-notes/{$creditId}/lines", [
        'lines' => [
            ['invoice_line_id' => $lineA->id, 'qty_to_credit' => 2],
            ['invoice_line_id' => $lineB->id, 'qty_to_credit' => 1.5],
        ],
    ])->assertOk()
        ->assertJsonPath('data.amount_minor', 50000)
        ->assertJsonPath('data.lines.0.qty_to_credit', 2);

    expect($response->json('data.lines'))->toHaveCount(2);

    $creditNote = CreditNote::findOrFail($creditId);
    expect($creditNote->lines()->count())->toBe(2);
    expect($creditNote->amount_minor)->toBe(50000);
});

it('prevents exceeding remaining quantities when other credits exist', function (): void {
    [$plan, $company, $user] = prepareCreditNotePlanContext();

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
        'subtotal' => 800.00,
        'tax_amount' => 0,
        'total' => 800.00,
        'currency' => 'USD',
    ]);

    $line = InvoiceLine::factory()->for($invoice)->create([
        'quantity' => 5,
        'unit_price' => 120,
        'unit_price_minor' => 12000,
    ]);

    $existingCredit = CreditNote::factory()->create([
        'company_id' => $company->id,
        'invoice_id' => $invoice->id,
        'purchase_order_id' => $purchaseOrder->id,
        'currency' => 'USD',
        'amount' => 480.00,
        'amount_minor' => 48000,
        'status' => CreditNoteStatus::Issued,
    ]);

    CreditNoteLine::create([
        'credit_note_id' => $existingCredit->id,
        'invoice_line_id' => $line->id,
        'qty_to_credit' => 4,
        'qty_invoiced' => $line->quantity,
        'unit_price_minor' => $line->unit_price_minor ?? 0,
        'line_total_minor' => 48000,
        'currency' => 'USD',
        'uom' => $line->uom,
        'description' => $line->description,
    ]);

    actingAs($user);

    $creditId = postJson("/api/credit-notes/invoices/{$invoice->id}", [
        'reason' => 'Follow-up variance',
        'amount' => 120.00,
    ])->json('data.id');

    putJson("/api/credit-notes/{$creditId}/lines", [
        'lines' => [
            ['invoice_line_id' => $line->id, 'qty_to_credit' => 2],
        ],
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Validation failed');
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
        'currency' => 'USD',
    ]);

    actingAs($user);

    postJson("/api/credit-notes/invoices/{$invoice->id}", [
        'reason' => 'Test credit',
        'amount' => 120.00,
    ])->assertStatus(402)
        ->assertJsonPath('message', 'Upgrade required to access credit notes.');
});
