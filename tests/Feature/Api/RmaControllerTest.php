<?php

use App\Enums\CreditNoteStatus;
use App\Enums\RmaStatus;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Rma;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

function prepareRmaContext(array $planOverrides = [], array $companyOverrides = [], array $userOverrides = []): array
{
    $plan = Plan::factory()->create(array_merge([
        'code' => 'rma-plan-'.str()->uuid()->toString(),
        'rma_enabled' => true,
        'rma_monthly_limit' => 5,
    ], $planOverrides));

    $company = Company::factory()->create(array_merge([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'status' => 'active',
    ], $companyOverrides));

    $user = User::factory()->create(array_merge([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ], $userOverrides));

    $company->owner_user_id = $user->id;
    $company->save();

    Subscription::factory()->for($company)->create([
        'stripe_status' => 'active',
        'ends_at' => null,
    ]);

    return [$plan, $company, $user];
}

beforeEach(function (): void {
    Config::set('documents.disk', 's3');
    Storage::fake('s3');
    Mail::fake();
});

it('allows buyers to submit an RMA with attachments', function (): void {
    [$plan, $company, $user] = prepareRmaContext();

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'confirmed',
    ]);

    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
    ]);

    actingAs($user);

    $payload = [
        'reason' => 'Damaged components on delivery',
        'resolution_requested' => 'replacement',
        'attachments' => [
            UploadedFile::fake()->create('defect-photo.jpg', 256, 'image/jpeg'),
            UploadedFile::fake()->create('inspection-report.pdf', 256, 'application/pdf'),
        ],
    ];

    $response = postJson("/api/rmas/purchase-orders/{$purchaseOrder->id}", $payload);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', RmaStatus::Raised->value);

    $rmaId = $response->json('data.id');
    expect($rmaId)->not->toBeNull();

    $rma = Rma::find($rmaId);
    expect($rma)->not->toBeNull();
    expect($rma->documents()->count())->toBe(2);

    $document = $rma->documents()->first();
    expect($document)->not->toBeNull();
    expect(Storage::disk('s3')->exists($document->path))->toBeTrue();

    expect($company->fresh()->rma_monthly_used)->toBe(1);

    expect(Notification::query()
        ->where('company_id', $company->id)
        ->where('event_type', 'rma.raised')
        ->count())->toBeGreaterThan(0);
});

it('allows quality reviewers to approve an RMA and closes it', function (): void {
    [$plan, $company, $submitter] = prepareRmaContext();

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'confirmed',
    ]);

    actingAs($submitter);

    $rmaResponse = postJson("/api/rmas/purchase-orders/{$purchaseOrder->id}", [
        'reason' => 'Incorrect calibration',
        'resolution_requested' => 'credit',
        'attachments' => [UploadedFile::fake()->create('evidence.png', 128, 'image/png')],
    ])->assertCreated();

    $rmaId = $rmaResponse->json('data.id');
    $rma = Rma::findOrFail($rmaId);

    $reviewer = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'finance',
    ]);

    actingAs($reviewer);

    $reviewResponse = postJson("/api/rmas/{$rma->id}/review", [
        'decision' => 'approve',
        'comment' => 'Approved for credit issuance',
    ]);

    $reviewResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', RmaStatus::Closed->value)
        ->assertJsonPath('data.review_outcome', 'approved');

    expect(Notification::query()
        ->where('event_type', 'rma.reviewed')
        ->where('entity_id', $rma->id)
        ->exists())->toBeTrue();

    expect(Notification::query()
        ->where('event_type', 'rma.closed')
        ->where('entity_id', $rma->id)
        ->exists())->toBeTrue();
});

it('rejects RMAs for undelivered orders or invalid attachments', function (): void {
    [$plan, $company, $user] = prepareRmaContext();

    $undeliveredPo = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'draft',
    ]);

    actingAs($user);

    postJson("/api/rmas/purchase-orders/{$undeliveredPo->id}", [
        'reason' => 'Attempt before delivery',
        'resolution_requested' => 'refund',
    ])->assertStatus(422)
        ->assertJsonPath('status', 'error');

    $deliveredPo = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'confirmed',
    ]);

    postJson("/api/rmas/purchase-orders/{$deliveredPo->id}", [
        'reason' => 'Bad packaging',
        'resolution_requested' => 'replacement',
        'attachments' => [UploadedFile::fake()->create('malware.exe', 1, 'application/octet-stream')],
    ])->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonStructure(['errors' => ['attachments.0']]);
});

it('applies plan gating when RMAs are disabled or limit exceeded', function (): void {
    [$plan, $company, $user] = prepareRmaContext([
        'rma_enabled' => false,
    ]);

    $po = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'confirmed',
    ]);

    actingAs($user);

    postJson("/api/rmas/purchase-orders/{$po->id}", [
        'reason' => 'Plan disabled',
        'resolution_requested' => 'repair',
    ])->assertStatus(402)
        ->assertJsonPath('message', 'Upgrade required to access RMAs.');

    $plan->update(['rma_enabled' => true, 'rma_monthly_limit' => 1]);
    $company->update(['rma_monthly_used' => 1]);
    $user = $user->fresh();
    actingAs($user);

    postJson("/api/rmas/purchase-orders/{$po->id}", [
        'reason' => 'Limit reached',
        'resolution_requested' => 'repair',
    ])->assertStatus(402)
        ->assertJsonPath('message', 'Upgrade required to file additional RMAs this month.');
});

it('creates a draft credit note when a credit RMA is approved', function (): void {
    [$plan, $company, $submitter] = prepareRmaContext([
        'credit_notes_enabled' => true,
    ]);

    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'status' => 'confirmed',
    ]);

    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'quantity' => 5,
        'unit_price' => 120,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'supplier_id' => $supplier->id,
        'subtotal' => 600,
        'tax_amount' => 0,
        'total' => 600,
        'currency' => 'USD',
    ]);

    InvoiceLine::factory()->create([
        'invoice_id' => $invoice->id,
        'po_line_id' => $poLine->id,
        'quantity' => 5,
        'unit_price' => 120,
    ]);

    actingAs($submitter);

    $createResponse = postJson("/api/rmas/purchase-orders/{$purchaseOrder->id}", [
        'reason' => 'Received units damaged',
        'resolution_requested' => 'credit',
        'purchase_order_line_id' => $poLine->id,
        'defect_qty' => 2,
    ])->assertCreated();

    $rmaId = $createResponse->json('data.id');
    $rma = Rma::findOrFail($rmaId);

    $reviewer = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'finance',
    ]);

    actingAs($reviewer);

    postJson("/api/rmas/{$rma->id}/review", [
        'decision' => 'approve',
        'comment' => 'Issuing credit',
    ])->assertOk();

    $rma->refresh();

    expect($rma->credit_note_id)->not->toBeNull();

    $creditNote = CreditNote::findOrFail($rma->credit_note_id);

    expect($creditNote->status->value ?? $creditNote->status)->toBe(CreditNoteStatus::Draft->value)
        ->and((float) $creditNote->amount)->toBe(240.0)
        ->and($creditNote->invoice_id)->toBe($invoice->id)
        ->and($creditNote->purchase_order_id)->toBe($purchaseOrder->id);
});
