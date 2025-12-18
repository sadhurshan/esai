<?php

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Enums\DocumentCategory;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Document;
use App\Models\GoodsReceiptNote;
use App\Models\Invoice;
use App\Models\Ncr;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\RFQ;
use App\Models\Rma;
use App\Models\Subscription;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['documents.disk' => 'public']);
});

it('stores a document for an rfq and returns resource data', function (): void {
    Storage::fake('public');

    [$company] = createActiveCompanyUser();

    $rfq = RFQ::factory()->for($company)->create([
        'status' => 'draft',
        'created_by' => auth()->id(),
    ]);

    $file = UploadedFile::fake()->create('drawing.pdf', 256, 'application/pdf');

    $response = $this->postJson('/api/documents', [
        'entity' => 'rfq',
        'entity_id' => $rfq->id,
        'kind' => 'rfq',
        'category' => 'technical',
        'visibility' => 'company',
        'file' => $file,
        'meta' => ['label' => 'Initial CAD'],
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.kind', 'rfq')
        ->assertJsonPath('data.category', 'technical')
        ->assertJsonPath('data.documentable_id', $rfq->id);

    $stored = Document::first();

    expect($stored)->not->toBeNull()
        ->and($stored->company_id)->toBe($company->id)
        ->and($stored->documentable_id)->toBe($rfq->id)
        ->and($stored->kind)->toBe('rfq')
        ->and($stored->category)->toBe('technical')
        ->and($stored->meta['label'])->toBe('Initial CAD');

    Storage::disk('public')->assertExists($stored->path);
});

it('allows supplier personas to upload documents for buyer rfqs', function (): void {
    Storage::fake('public');

    $buyerCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::None->value,
    ]);

    $supplierContext = createSupplierPersonaForBuyer($buyerCompany);
    $supplierUser = $supplierContext['user'];
    $supplierPersona = $supplierContext['persona'];

    $rfq = CompanyContext::forCompany($buyerCompany->id, static function () use ($buyerCompany, $supplierUser): RFQ {
        return RFQ::factory()->create([
            'company_id' => $buyerCompany->id,
            'status' => 'open',
            'created_by' => $supplierUser->id,
        ]);
    });

    actingAs($supplierUser);

    $file = UploadedFile::fake()->create('supplier-quote.pdf', 150, 'application/pdf');

    $response = $this
        ->withHeaders(['X-Active-Persona' => $supplierPersona['key']])
        ->postJson('/api/documents', [
            'entity' => 'rfq',
            'entity_id' => $rfq->id,
            'kind' => 'rfq',
            'category' => DocumentCategory::Technical->value,
            'visibility' => 'company',
            'file' => $file,
            'meta' => ['label' => 'Supplier attachment'],
        ]);

    $response->assertOk()
        ->assertJsonPath('data.documentable_id', $rfq->id);

    $document = Document::first();

    expect($document)->not->toBeNull()
        ->and($document->company_id)->toBe($buyerCompany->id)
        ->and($document->documentable_id)->toBe($rfq->id)
        ->and($document->meta['label'])->toBe('Supplier attachment');
});

it('allows supplier personas to delete their uploaded documents', function (): void {
    Storage::fake('public');

    $buyerCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::None->value,
    ]);

    $supplierContext = createSupplierPersonaForBuyer($buyerCompany);
    $supplierUser = $supplierContext['user'];
    $supplierPersona = $supplierContext['persona'];

    $rfq = CompanyContext::forCompany($buyerCompany->id, static function () use ($buyerCompany, $supplierUser): RFQ {
        return RFQ::factory()->create([
            'company_id' => $buyerCompany->id,
            'status' => 'open',
            'created_by' => $supplierUser->id,
        ]);
    });

    actingAs($supplierUser);

    $file = UploadedFile::fake()->create('supplier-quote.pdf', 150, 'application/pdf');

    $uploadResponse = $this
        ->withHeaders(['X-Active-Persona' => $supplierPersona['key']])
        ->postJson('/api/documents', [
            'entity' => 'rfq',
            'entity_id' => $rfq->id,
            'kind' => 'rfq',
            'category' => DocumentCategory::Technical->value,
            'visibility' => 'company',
            'file' => $file,
        ]);

    $uploadResponse->assertOk();

    $document = Document::first();

    expect($document)->not->toBeNull();

    $deleteResponse = $this
        ->withHeaders(['X-Active-Persona' => $supplierPersona['key']])
        ->deleteJson("/api/documents/{$document->id}");

    $deleteResponse->assertOk()->assertJsonPath('message', 'Document deleted.');

    expect(Document::count())->toBe(0);
});


it('attaches QA workflow documents to supported entities', function (string $entityKey): void {
    Storage::fake('public');

    [$company, $user] = createActiveCompanyUser();

    $record = match ($entityKey) {
        'grn' => createGoodsReceiptNoteForCompany($company, $user),
        'ncr' => createNcrForCompany($company, $user),
        'rma' => createRmaForCompany($company, $user),
        'credit_note' => createCreditNoteForCompany($company),
        default => throw new InvalidArgumentException('Unsupported entity key'),
    };

    $file = UploadedFile::fake()->create("qa-{$entityKey}.pdf", 200, 'application/pdf');

    $response = $this->postJson('/api/documents', [
        'entity' => $entityKey,
        'entity_id' => $record->id,
        'kind' => 'other',
        'category' => DocumentCategory::Qa->value,
        'visibility' => 'company',
        'file' => $file,
        'meta' => ['label' => strtoupper($entityKey).' attachment'],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.documentable_id', $record->id);

    $stored = Document::query()
        ->where('documentable_type', $record->getMorphClass())
        ->where('documentable_id', $record->id)
        ->first();

    expect($stored)->not->toBeNull();
})->with([
    'grn',
    'ncr',
    'rma',
    'credit_note',
]);

it('returns not found when attempting to attach documents to another company entity', function (): void {
    Storage::fake('public');

    [$ownerCompany, $user, $plan] = createActiveCompanyUser();

    $otherCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'plan_code' => $plan->code,
        'supplier_status' => 'approved',
        'is_verified' => true,
        'rfqs_monthly_used' => 0,
        'invoices_monthly_used' => 0,
        'storage_used_mb' => 0,
    ]);

    $foreignRfq = RFQ::factory()->for($otherCompany)->create([
        'status' => 'draft',
    ]);

    $file = UploadedFile::fake()->create('spec.pdf', 128, 'application/pdf');

    $response = $this->postJson('/api/documents', [
        'entity' => 'rfq',
        'entity_id' => $foreignRfq->id,
        'kind' => 'rfq',
        'category' => 'technical',
        'visibility' => 'company',
        'file' => $file,
    ]);

    $response->assertNotFound();
    expect(Document::count())->toBe(0);
});

function createActiveCompanyUser(): array
{
    $plan = Plan::factory()->create([
        'code' => 'starter',
    ]);

    $company = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'plan_code' => $plan->code,
        'supplier_status' => 'approved',
        'is_verified' => true,
        'rfqs_monthly_used' => 0,
        'invoices_monthly_used' => 0,
        'storage_used_mb' => 0,
        'registration_no' => 'REG-'.Str::upper(Str::random(4)),
        'tax_id' => 'TAX-'.Str::upper(Str::random(4)),
        'country' => 'US',
        'email_domain' => 'example.org',
        'primary_contact_name' => 'Example Owner',
        'primary_contact_email' => 'owner@example.org',
        'primary_contact_phone' => '+1-555-0100',
    ]);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'stripe_status' => 'active',
    ]);

    $user = User::factory()->for($company)->create([
        'role' => 'buyer_admin',
    ]);

    actingAs($user);

    return [$company, $user, $plan];
}

function createPurchaseOrderForCompany(Company $company): PurchaseOrder
{
    return PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'confirmed',
    ]);
}

function createGoodsReceiptNoteForCompany(Company $company, User $inspector): GoodsReceiptNote
{
    $purchaseOrder = createPurchaseOrderForCompany($company);

    return GoodsReceiptNote::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'inspected_by_id' => $inspector->id,
        'status' => 'pending',
    ]);
}

function createNcrForCompany(Company $company, User $user): Ncr
{
    $goodsReceiptNote = createGoodsReceiptNoteForCompany($company, $user);
    $line = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $goodsReceiptNote->purchase_order_id,
    ]);

    return Ncr::factory()->create([
        'company_id' => $company->id,
        'goods_receipt_note_id' => $goodsReceiptNote->id,
        'purchase_order_line_id' => $line->id,
        'raised_by_id' => $user->id,
    ]);
}

function createRmaForCompany(Company $company, User $user): Rma
{
    $purchaseOrder = createPurchaseOrderForCompany($company);

    return Rma::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'submitted_by' => $user->id,
    ]);
}

function createCreditNoteForCompany(Company $company): CreditNote
{
    $purchaseOrder = createPurchaseOrderForCompany($company);
    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
    ]);

    return CreditNote::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'invoice_id' => $invoice->id,
    ]);
}
