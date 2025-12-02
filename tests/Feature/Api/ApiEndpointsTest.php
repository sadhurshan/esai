<?php

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Document;
use App\Models\RFQ;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\RfqItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['documents.disk' => 'public']);
});

it('returns suppliers with envelope and pagination metadata', function () {
    $companies = Company::factory()->count(3)->create([
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'directory_visibility' => 'public',
        'supplier_profile_completed_at' => now(),
    ]);

    $companies->each(function (Company $company): void {
        Supplier::factory()->for($company)->create([
            'status' => 'approved',
        ]);
    });

    $response = $this->getJson('/api/suppliers');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', null)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'items',
                'meta',
            ],
            'meta' => [
                'cursor' => ['next_cursor', 'prev_cursor', 'has_next', 'has_prev'],
            ],
        ]);

    $response->assertJsonCount(3, 'data.items')
        ->assertJsonPath('meta.cursor.next_cursor', null)
        ->assertJsonPath('meta.cursor.prev_cursor', null)
        ->assertJsonPath('data.meta.per_page', 10);
});

beforeEach(function (): void {
    Plan::factory()->create([
        'code' => 'starter',
        'name' => 'Starter',
        'rfqs_per_month' => 10,
        'users_max' => 5,
        'storage_gb' => 10,
    ]);
});

function actingAsSubscribedUser(): User
{
    $company = Company::factory()->create([
        'plan_code' => 'starter',
        'rfqs_monthly_used' => 0,
        'supplier_status' => 'approved',
        'is_verified' => true,
        'registration_no' => 'REG-001',
        'tax_id' => 'TAX-001',
        'country' => 'US',
        'email_domain' => 'example.com',
        'primary_contact_name' => 'Primary Contact',
        'primary_contact_email' => 'primary@example.com',
        'primary_contact_phone' => '+1-555-0100',
        'status' => CompanyStatus::Active,
    ]);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'stripe_status' => 'active',
    ]);

    $user = User::factory()->for($company)->create();

    actingAs($user);

    return $user;
}

it('creates an rfq with cad upload', function () {
    Storage::fake('public');
    actingAsSubscribedUser();

    $payload = [
        'title' => 'Gearbox Housing',
        'method' => 'cnc',
        'material' => 'aluminum 7075-t6',
        'delivery_location' => 'Elements Supply AI',
        'due_at' => now()->addDays(14)->toIso8601String(),
        'open_bidding' => true,
        'notes' => 'Include inspection report.',
        'cad' => UploadedFile::fake()->create('housing.step', 50),
        'items' => [
            [
                'part_number' => 'Valve Body',
                'description' => 'CNC machined, tolerance +/-0.01mm',
                'qty' => 60,
                'uom' => 'pcs',
                'target_price' => 125.50,
                'method' => 'cnc',
                'material' => 'Aluminum 7075-T6',
                'tolerance' => '+/-0.01 mm',
                'finish' => 'Anodized',
                'specs_json' => ['dwg' => 'valve-body.dwg'],
            ],
            [
                'part_number' => 'Cover Plate',
                'description' => 'Anodized exterior',
                'qty' => 60,
                'uom' => 'pcs',
                'target_price' => 48.20,
                'method' => 'sheet_metal',
                'material' => 'Stainless Steel 304',
                'tolerance' => null,
                'finish' => 'Powder Coat',
            ],
        ],
    ];

    $response = $this->postJson('/api/rfqs', $payload);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure([
            'data' => [
                'id',
                'number',
                'cad_document_id',
            ],
        ]);

    $rfq = RFQ::with(['items', 'cadDocument'])->first();
    expect($rfq)->not->toBeNull()
        ->and($rfq->items)->toHaveCount(2)
        ->and($rfq->items->first()->part_number)->toBe('Valve Body')
        ->and($rfq->items->first()->qty)->toBe(60)
        ->and($rfq->items->first()->company_id)->toBe($rfq->company_id)
        ->and($rfq->items->first()->specs_json)->toBe(['dwg' => 'valve-body.dwg'])
        ->and($rfq->cad_document_id)->not->toBeNull();

    $document = $rfq->cadDocument;
    expect($document)->not->toBeNull()
        ->and($document->kind)->toBe('cad')
        ->and($document->category)->toBe('technical');

    Storage::disk('public')->assertExists($document->path);
    expect(Document::count())->toBe(1);
});

it('replaces the rfq cad document on update', function () {
    Storage::fake('public');
    actingAsSubscribedUser();

    $createPayload = [
        'title' => 'Precision Bracket',
        'method' => 'cnc',
        'material' => 'aluminum 6061',
        'delivery_location' => 'Elements Supply AI',
        'due_at' => now()->addDays(7)->toIso8601String(),
        'items' => [[
            'part_number' => 'Bracket',
            'qty' => 10,
            'method' => 'cnc',
            'material' => 'aluminum 6061',
        ]],
        'cad' => UploadedFile::fake()->create('initial.step', 40),
    ];

    $this->postJson('/api/rfqs', $createPayload)->assertCreated();

    $rfq = RFQ::with('cadDocument')->firstOrFail();
    $originalDocumentId = $rfq->cad_document_id;

    $this->putJson("/api/rfqs/{$rfq->id}", [
        'cad' => UploadedFile::fake()->create('replacement.step', 30),
    ])->assertOk();

    $rfq->refresh()->load('cadDocument');

    expect($rfq->cad_document_id)->not->toEqual($originalDocumentId)
        ->and($rfq->cadDocument?->version_number)->toBe(2);

    $documents = Document::query()
        ->where('documentable_type', $rfq->getMorphClass())
        ->where('documentable_id', $rfq->id)
        ->orderBy('version_number')
        ->get();

    expect($documents)->toHaveCount(2)
        ->and($documents->first()->meta['status'] ?? null)->toBe('superseded');

    expect(
        AuditLog::query()
            ->where('entity_type', RFQ::class)
            ->where('entity_id', $rfq->id)
            ->where('action', 'updated')
            ->exists()
    )->toBeTrue();
});

it('soft deletes cad documents when rfqs are deleted', function () {
    Storage::fake('public');
    actingAsSubscribedUser();

    $payload = [
        'title' => 'Fixture Plate',
        'method' => 'cnc',
        'material' => 'steel',
        'delivery_location' => 'Elements Supply AI',
        'due_at' => now()->addDays(14)->toIso8601String(),
        'items' => [[
            'part_number' => 'Plate',
            'qty' => 5,
            'method' => 'cnc',
            'material' => 'Steel',
        ]],
        'cad' => UploadedFile::fake()->create('fixture.step', 35),
    ];

    $this->postJson('/api/rfqs', $payload)->assertCreated();

    $rfq = RFQ::with('cadDocument')->firstOrFail();
    $documentId = $rfq->cad_document_id;

    $this->deleteJson("/api/rfqs/{$rfq->id}")->assertOk();

    $rfq = RFQ::withTrashed()->findOrFail($rfq->id);
    expect($rfq->cad_document_id)->toBeNull();

    $document = Document::withTrashed()->find($documentId);
    expect($document)->not->toBeNull()
        ->and($document?->trashed())->toBeTrue();
});

it('creates a quote with items and attachment upload', function () {
    Storage::fake('public');

    $user = actingAsSubscribedUser();

    $rfq = RFQ::factory()->for($user->company)->create([
        'open_bidding' => true,
        'status' => RFQ::STATUS_OPEN,
        'due_at' => now()->addDays(5),
        'close_at' => now()->addDays(5),
    ]);
    $items = RfqItem::factory()->count(2)->for($rfq)->sequence(
        ['line_no' => 1],
        ['line_no' => 2],
    )->create();
    $supplier = Supplier::factory()
        ->for($user->company)
        ->create([
            'status' => 'approved',
        ]);

    $payload = [
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'currency' => 'USD',
        'unit_price' => 145.50,
        'lead_time_days' => 21,
        'min_order_qty' => 10,
        'note' => 'Includes expedited shipping.',
        'items' => $items->map(fn (RfqItem $item) => [
            'rfq_item_id' => $item->id,
            'unit_price' => 72.75,
            'lead_time_days' => 21,
        ])->toArray(),
        'attachment' => UploadedFile::fake()->create('quote.pdf', 80),
    ];

    $response = $this->postJson('/api/quotes', $payload);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure([
            'data' => [
                'id',
                'items',
            ],
        ]);

    $quote = Quote::with(['items', 'documents'])->first();
    expect($quote)->not->toBeNull()
        ->and($quote->items)->toHaveCount(2)
        ->and($quote->documents)->toHaveCount(1);

    $document = $quote->documents->first();
    Storage::disk('public')->assertExists($document->path);
    expect(QuoteItem::count())->toBe(2);
});

it('returns validation errors for invalid rfq payloads', function () {
    actingAsSubscribedUser();

    $response = $this->postJson('/api/rfqs', []);

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Validation failed')
        ->assertJsonStructure(['errors' => ['title']]);
});

it('returns validation errors for invalid quote payloads', function () {
    $user = actingAsSubscribedUser();

    $rfq = RFQ::factory()->for($user->company)->create([
        'open_bidding' => true,
    ]);

    $supplier = Supplier::factory()
        ->for($user->company)
        ->create([
            'status' => 'approved',
        ]);

    $response = $this->postJson('/api/quotes', [
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Validation failed')
        ->assertJsonStructure(['errors' => ['currency']]);
});
