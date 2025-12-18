<?php

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\RfqInvitation;
use App\Models\RfqItem;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\actingAs;


beforeEach(function (): void {
    config(['documents.disk' => 'public']);
});

it('allows an approved supplier to submit a quote to another company\'s rfq', function (): void {
    Storage::fake('public');

    $plan = Plan::factory()->create([
        'code' => 'starter',
    ]);

    $buyerCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::None->value,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'rfqs_monthly_used' => 0,
        'storage_used_mb' => 0,
    ]);

    $buyerCustomer = Customer::factory()->create([
        'company_id' => $buyerCompany->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $buyerCompany->id,
        'customer_id' => $buyerCustomer->id,
        'stripe_status' => 'active',
    ]);

    $buyerUser = User::factory()->create([
        'role' => 'buyer_admin',
        'company_id' => $buyerCompany->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $buyerCompany->id,
        'user_id' => $buyerUser->id,
        'role' => $buyerUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplierCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'is_verified' => true,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'rfqs_monthly_used' => 0,
        'storage_used_mb' => 0,
    ]);

    $supplierCustomer = Customer::factory()->create([
        'company_id' => $supplierCompany->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $supplierCompany->id,
        'customer_id' => $supplierCustomer->id,
        'stripe_status' => 'active',
    ]);

    $supplierUser = User::factory()->create([
        'role' => 'supplier_admin',
        'company_id' => $supplierCompany->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $supplierCompany->id,
        'user_id' => $supplierUser->id,
        'role' => $supplierUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplier = Supplier::factory()
        ->for($supplierCompany)
        ->create([
            'status' => 'approved',
        ]);

    $rfq = RFQ::factory()
        ->for($buyerCompany)
        ->create([
            'status' => 'open',
            'is_open_bidding' => true,
            'created_by' => $buyerUser->id,
            'publish_at' => now()->subDay(),
            'due_at' => now()->addDays(20),
            'close_at' => now()->addDays(20),
        ]);

    $items = RfqItem::factory()->count(2)->create([
        'rfq_id' => $rfq->id,
    ]);

    actingAs($supplierUser);

    $payload = [
        'supplier_id' => $supplier->id,
        'currency' => 'USD',
        'unit_price' => 145.50,
        'lead_time_days' => 18,
        'min_order_qty' => 5,
        'note' => 'Includes expedited shipping.',
        'items' => $items->map(fn (RfqItem $item) => [
            'rfq_item_id' => $item->id,
            'unit_price' => 72.75,
            'lead_time_days' => 18,
        ])->toArray(),
        'attachment' => UploadedFile::fake()->create('quote.pdf', 200),
    ];

    $response = $this->postJson("/api/rfqs/{$rfq->id}/quotes", $payload);

    $response
        ->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.supplier.id', $supplier->id)
        ->assertJsonPath('data.rfq_id', $rfq->id);

    $quote = Quote::with(['items', 'documents'])->first();

    expect($quote)->not->toBeNull()
        ->and($quote->company_id)->toBe($rfq->company_id)
        ->and($quote->supplier_id)->toBe($supplier->id)
        ->and($quote->status)->toBe('draft')
        ->and($quote->items)->toHaveCount(2)
        ->and($quote->documents)->toHaveCount(1);

    $submitResponse = $this->putJson("/api/rfqs/{$rfq->id}/quotes/{$quote->id}");

    $submitResponse
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.submitted_by', $supplierUser->id);

    $document = $quote->documents->first();

    expect($document)->not->toBeNull()
    ->and($document->company_id)->toBe($rfq->company_id)
        ->and($document->documentable_id)->toBe($quote->id);

    Storage::disk('public')->assertExists($document->path);
});

it('infers the supplier_id when a supplier persona submits a quote without including it', function (): void {
    $buyerCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::None->value,
    ]);

    $supplierContext = createSupplierPersonaForBuyer($buyerCompany);

    $supplierUser = $supplierContext['user'];
    $supplierPersona = $supplierContext['persona'];
    $supplier = $supplierContext['supplier'];

    $rfq = CompanyContext::forCompany($buyerCompany->id, function () use ($buyerCompany, $supplier): RFQ {
        $rfq = RFQ::factory()->create([
            'company_id' => $buyerCompany->id,
            'status' => RFQ::STATUS_OPEN,
            'open_bidding' => false,
            'created_by' => null,
            'publish_at' => now()->subDay(),
            'due_at' => now()->addDays(5),
            'close_at' => now()->addDays(5),
        ]);

        RfqInvitation::factory()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
        ]);

        return $rfq;
    });

    $items = CompanyContext::forCompany($buyerCompany->id, fn () => RfqItem::factory()->count(2)->create([
        'rfq_id' => $rfq->id,
    ]));

    actingAs($supplierUser);

    $payload = [
        'currency' => 'USD',
        'unit_price' => 52.75,
        'lead_time_days' => 14,
        'items' => $items->map(fn (RfqItem $item) => [
            'rfq_item_id' => $item->id,
            'unit_price' => 26.37,
            'lead_time_days' => 14,
        ])->toArray(),
    ];

    $response = $this
        ->withHeaders(['X-Active-Persona' => $supplierPersona['key']])
        ->postJson("/api/rfqs/{$rfq->id}/quotes", $payload);

    $response
        ->assertCreated()
        ->assertJsonPath('data.supplier.id', $supplier->id)
        ->assertJsonPath('data.status', 'draft');

    $quote = Quote::query()->with('items')->first();

    expect($quote)->not->toBeNull()
        ->and($quote->supplier_id)->toBe($supplier->id)
        ->and($quote->items)->toHaveCount(2);
});

it('derives aggregate lead time when supplier payload omits it', function (): void {
    $buyerCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::None->value,
    ]);

    $supplierContext = createSupplierPersonaForBuyer($buyerCompany);

    $supplierUser = $supplierContext['user'];
    $supplierPersona = $supplierContext['persona'];
    $supplier = $supplierContext['supplier'];

    $rfq = CompanyContext::forCompany($buyerCompany->id, function () use ($buyerCompany, $supplier): RFQ {
        $rfq = RFQ::factory()->create([
            'company_id' => $buyerCompany->id,
            'status' => RFQ::STATUS_OPEN,
            'open_bidding' => false,
            'created_by' => null,
            'publish_at' => now()->subDay(),
            'due_at' => now()->addDays(5),
            'close_at' => now()->addDays(5),
        ]);

        RfqInvitation::factory()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
        ]);

        return $rfq;
    });

    $items = CompanyContext::forCompany($buyerCompany->id, fn () => RfqItem::factory()->count(2)->create([
        'rfq_id' => $rfq->id,
    ]));

    actingAs($supplierUser);

    $payload = [
        'currency' => 'USD',
        'items' => $items->values()->map(fn (RfqItem $item, int $index) => [
            'rfq_item_id' => $item->id,
            'unit_price_minor' => 1250 * ($index + 1),
            'lead_time_days' => 5 + ($index * 4),
        ])->all(),
    ];

    $response = $this
        ->withHeaders(['X-Active-Persona' => $supplierPersona['key']])
        ->postJson("/api/rfqs/{$rfq->id}/quotes", $payload);

    $response
        ->assertCreated()
        ->assertJsonPath('data.supplier.id', $supplier->id)
        ->assertJsonPath('data.lead_time_days', 9);

    $quote = Quote::query()->with('items')->first();

    expect($quote)->not->toBeNull()
        ->and($quote->lead_time_days)->toBe(9)
        ->and($quote->items)->toHaveCount(2);
});

it('increments the quote revision when the same supplier submits another quote to the rfq', function (): void {
    $buyerCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::None->value,
    ]);

    $supplierContext = createSupplierPersonaForBuyer($buyerCompany);

    $supplierUser = $supplierContext['user'];
    $supplierPersona = $supplierContext['persona'];
    $supplier = $supplierContext['supplier'];

    $rfq = CompanyContext::forCompany($buyerCompany->id, function () use ($buyerCompany, $supplier): RFQ {
        $rfq = RFQ::factory()->create([
            'company_id' => $buyerCompany->id,
            'status' => RFQ::STATUS_OPEN,
            'open_bidding' => false,
            'created_by' => null,
            'publish_at' => now()->subDay(),
            'due_at' => now()->addDays(5),
            'close_at' => now()->addDays(5),
        ]);

        RfqInvitation::factory()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
        ]);

        return $rfq;
    });

    $items = CompanyContext::forCompany($buyerCompany->id, fn () => RfqItem::factory()->count(1)->create([
        'rfq_id' => $rfq->id,
    ]));

    actingAs($supplierUser);

    $basePayload = [
        'currency' => 'USD',
        'lead_time_days' => 12,
        'items' => $items->map(fn (RfqItem $item) => [
            'rfq_item_id' => $item->id,
            'unit_price' => 42.15,
            'lead_time_days' => 12,
        ])->values()->all(),
    ];

    $this
        ->withHeaders(['X-Active-Persona' => $supplierPersona['key']])
        ->postJson("/api/rfqs/{$rfq->id}/quotes", $basePayload)
        ->assertCreated()
        ->assertJsonPath('data.revision_no', 1);

    $secondPayload = $basePayload;
    $secondPayload['lead_time_days'] = 15;
    $secondPayload['items'][0]['lead_time_days'] = 15;

    $this
        ->withHeaders(['X-Active-Persona' => $supplierPersona['key']])
        ->postJson("/api/rfqs/{$rfq->id}/quotes", $secondPayload)
        ->assertCreated()
        ->assertJsonPath('data.revision_no', 2);

    $revisions = CompanyContext::bypass(static fn () => Quote::query()
        ->orderBy('revision_no')
        ->pluck('revision_no')
        ->all());

    $quoteCount = CompanyContext::bypass(static fn () => Quote::query()->count());

    expect($revisions)->toBe([1, 2])
        ->and($quoteCount)->toBe(2);
});

it('allows supplier personas to withdraw their own quotes', function (): void {
    $buyerCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::None->value,
    ], [
        'quote_revisions_enabled' => true,
    ]);

    $supplierContext = createSupplierPersonaForBuyer($buyerCompany);

    $supplierUser = $supplierContext['user'];
    $supplierPersona = $supplierContext['persona'];
    $supplier = $supplierContext['supplier'];

    $rfq = CompanyContext::forCompany($buyerCompany->id, function () use ($buyerCompany, $supplier): RFQ {
        $rfq = RFQ::factory()->create([
            'company_id' => $buyerCompany->id,
            'status' => RFQ::STATUS_OPEN,
            'open_bidding' => false,
            'created_by' => null,
            'publish_at' => now()->subDay(),
            'due_at' => now()->addDays(10),
            'close_at' => now()->addDays(10),
        ]);

        RfqInvitation::factory()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
        ]);

        return $rfq;
    });

    $items = CompanyContext::forCompany($buyerCompany->id, fn () => RfqItem::factory()->count(1)->create([
        'rfq_id' => $rfq->id,
    ]));

    actingAs($supplierUser);

    $quoteResponse = $this
        ->withHeaders(['X-Active-Persona' => $supplierPersona['key']])
        ->postJson("/api/rfqs/{$rfq->id}/quotes", [
            'currency' => 'USD',
            'unit_price' => 42.15,
            'lead_time_days' => 12,
            'items' => $items->map(fn (RfqItem $item) => [
                'rfq_item_id' => $item->id,
                'unit_price' => 42.15,
                'lead_time_days' => 12,
            ])->toArray(),
        ])
        ->assertCreated();

    $quoteId = (int) $quoteResponse->json('data.id');

    $this
        ->withHeaders(['X-Active-Persona' => $supplierPersona['key']])
        ->putJson("/api/rfqs/{$rfq->id}/quotes/{$quoteId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');

    $withdrawResponse = $this
        ->withHeaders(['X-Active-Persona' => $supplierPersona['key']])
        ->patchJson("/api/rfqs/{$rfq->id}/quotes/{$quoteId}", [
            'reason' => 'Updated drawings coming.',
        ]);

    $withdrawResponse
        ->assertOk()
        ->assertJsonPath('data.status', 'withdrawn');
});

it('allows supplier personas to update draft quote details and attachments', function (): void {
    $buyerCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::None->value,
    ]);

    $supplierContext = createSupplierPersonaForBuyer($buyerCompany);

    $supplierUser = $supplierContext['user'];
    $supplierPersona = $supplierContext['persona'];
    $supplier = $supplierContext['supplier'];

    $rfq = CompanyContext::forCompany($buyerCompany->id, function () use ($buyerCompany, $supplier): RFQ {
        $rfq = RFQ::factory()->create([
            'company_id' => $buyerCompany->id,
            'status' => RFQ::STATUS_OPEN,
            'open_bidding' => false,
            'created_by' => null,
            'publish_at' => now()->subDay(),
            'due_at' => now()->addDays(10),
            'close_at' => now()->addDays(10),
        ]);

        RfqInvitation::factory()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
        ]);

        return $rfq;
    });

    $items = CompanyContext::forCompany($buyerCompany->id, fn () => RfqItem::factory()->count(1)->create([
        'rfq_id' => $rfq->id,
    ]));

    actingAs($supplierUser);

    $quoteResponse = $this
        ->withHeaders(['X-Active-Persona' => $supplierPersona['key']])
        ->postJson("/api/rfqs/{$rfq->id}/quotes", [
            'supplier_id' => $supplier->id,
            'currency' => 'USD',
            'lead_time_days' => 12,
            'min_order_qty' => 5,
            'note' => 'Initial scope',
            'items' => $items->map(fn (RfqItem $item) => [
                'rfq_item_id' => $item->id,
                'unit_price' => 42.15,
                'lead_time_days' => 12,
            ])->toArray(),
        ])
        ->assertCreated();

    $quoteId = (int) $quoteResponse->json('data.id');

    $document = Document::factory()->create([
        'company_id' => $buyerCompany->id,
        'documentable_type' => $rfq->getMorphClass(),
        'documentable_id' => $rfq->id,
        'category' => DocumentCategory::Commercial->value,
        'kind' => DocumentKind::Quote->value,
    ]);

    $updatePayload = [
        'currency' => 'EUR',
        'min_order_qty' => 25,
        'lead_time_days' => 15,
        'incoterm' => 'FCA',
        'payment_terms' => 'Net 45',
        'note' => 'Updated terms apply.',
        'attachments' => [$document->id],
    ];

    $this
        ->withHeaders(['X-Active-Persona' => $supplierPersona['key']])
        ->patchJson("/api/rfqs/{$rfq->id}/quotes/{$quoteId}/draft", $updatePayload)
        ->assertOk()
        ->assertJsonPath('data.currency', 'EUR')
        ->assertJsonPath('data.min_order_qty', 25)
        ->assertJsonPath('data.lead_time_days', 15)
        ->assertJsonPath('data.incoterm', 'FCA')
        ->assertJsonPath('data.payment_terms', 'Net 45');

    $quote = Quote::query()->with('documents')->find($quoteId);

    expect($quote)->not->toBeNull()
        ->and($quote->note)->toBe('Updated terms apply.')
        ->and($quote->min_order_qty)->toBe(25)
        ->and($quote->currency)->toBe('EUR');

    $document->refresh();

    expect($document->documentable_type)->toBe($quote->getMorphClass())
        ->and($document->documentable_id)->toBe($quoteId);
});

it('prevents draft detail updates once the quote is submitted', function (): void {
    $buyerCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::None->value,
    ]);

    $supplierContext = createSupplierPersonaForBuyer($buyerCompany);

    $supplierUser = $supplierContext['user'];
    $supplierPersona = $supplierContext['persona'];
    $supplier = $supplierContext['supplier'];

    $rfq = CompanyContext::forCompany($buyerCompany->id, function () use ($buyerCompany, $supplier): RFQ {
        $rfq = RFQ::factory()->create([
            'company_id' => $buyerCompany->id,
            'status' => RFQ::STATUS_OPEN,
            'open_bidding' => false,
            'created_by' => null,
            'publish_at' => now()->subDay(),
            'due_at' => now()->addDays(5),
            'close_at' => now()->addDays(5),
        ]);

        RfqInvitation::factory()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
        ]);

        return $rfq;
    });

    $items = CompanyContext::forCompany($buyerCompany->id, fn () => RfqItem::factory()->count(1)->create([
        'rfq_id' => $rfq->id,
    ]));

    actingAs($supplierUser);

    $quoteResponse = $this
        ->withHeaders(['X-Active-Persona' => $supplierPersona['key']])
        ->postJson("/api/rfqs/{$rfq->id}/quotes", [
            'supplier_id' => $supplier->id,
            'currency' => 'USD',
            'lead_time_days' => 12,
            'items' => $items->map(fn (RfqItem $item) => [
                'rfq_item_id' => $item->id,
                'unit_price' => 42.15,
                'lead_time_days' => 12,
            ])->toArray(),
        ])
        ->assertCreated();

    $quoteId = (int) $quoteResponse->json('data.id');

    $this
        ->withHeaders(['X-Active-Persona' => $supplierPersona['key']])
        ->putJson("/api/rfqs/{$rfq->id}/quotes/{$quoteId}")
        ->assertOk();

    $this
        ->withHeaders(['X-Active-Persona' => $supplierPersona['key']])
        ->patchJson("/api/rfqs/{$rfq->id}/quotes/{$quoteId}/draft", [
            'note' => 'Should fail',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only draft quotes can be edited.');
});

it('allows supplier personas to submit quotes via the standalone endpoint', function (): void {
    $buyerCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::None->value,
    ]);

    $supplierContext = createSupplierPersonaForBuyer($buyerCompany);

    $supplierUser = $supplierContext['user'];
    $supplierPersona = $supplierContext['persona'];
    $supplier = $supplierContext['supplier'];

    $rfq = CompanyContext::forCompany($buyerCompany->id, function () use ($buyerCompany, $supplier): RFQ {
        $rfq = RFQ::factory()->create([
            'company_id' => $buyerCompany->id,
            'status' => RFQ::STATUS_OPEN,
            'open_bidding' => false,
            'created_by' => null,
            'publish_at' => now()->subDay(),
            'due_at' => now()->addDays(10),
            'close_at' => now()->addDays(10),
        ]);

        RfqInvitation::factory()->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
        ]);

        return $rfq;
    });

    $items = CompanyContext::forCompany($buyerCompany->id, fn () => RfqItem::factory()->count(1)->create([
        'rfq_id' => $rfq->id,
    ]));

    actingAs($supplierUser);

    $quoteResponse = $this
        ->withHeaders(['X-Active-Persona' => $supplierPersona['key']])
        ->postJson("/api/rfqs/{$rfq->id}/quotes", [
            'currency' => 'USD',
            'unit_price' => 88.25,
            'lead_time_days' => 9,
            'items' => $items->map(fn (RfqItem $item) => [
                'rfq_item_id' => $item->id,
                'unit_price' => 88.25,
                'lead_time_days' => 9,
            ])->toArray(),
        ])
        ->assertCreated();

    $quoteId = (int) $quoteResponse->json('data.id');

    $this
        ->withHeaders(['X-Active-Persona' => $supplierPersona['key']])
        ->postJson("/api/quotes/{$quoteId}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.submitted_by', $supplierUser->id);
});

it('moves referenced rfq documents onto the quote attachments list', function (): void {
    Storage::fake('public');

    $plan = Plan::factory()->create([
        'code' => 'starter',
    ]);

    $buyerCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::None->value,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $buyerCustomer = Customer::factory()->create([
        'company_id' => $buyerCompany->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $buyerCompany->id,
        'customer_id' => $buyerCustomer->id,
        'stripe_status' => 'active',
    ]);

    $buyerUser = User::factory()->create([
        'role' => 'buyer_admin',
        'company_id' => $buyerCompany->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $buyerCompany->id,
        'user_id' => $buyerUser->id,
        'role' => $buyerUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplierCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'is_verified' => true,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $supplierCustomer = Customer::factory()->create([
        'company_id' => $supplierCompany->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $supplierCompany->id,
        'customer_id' => $supplierCustomer->id,
        'stripe_status' => 'active',
    ]);

    $supplierUser = User::factory()->create([
        'role' => 'supplier_admin',
        'company_id' => $supplierCompany->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $supplierCompany->id,
        'user_id' => $supplierUser->id,
        'role' => $supplierUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplier = Supplier::factory()
        ->for($supplierCompany)
        ->create([
            'status' => 'approved',
        ]);

    $rfq = RFQ::factory()
        ->for($buyerCompany)
        ->create([
            'status' => 'open',
            'is_open_bidding' => true,
            'created_by' => $buyerUser->id,
            'publish_at' => now()->subDay(),
            'due_at' => now()->addDays(7),
            'close_at' => now()->addDays(7),
        ]);

    $items = RfqItem::factory()->count(1)->create([
        'rfq_id' => $rfq->id,
    ]);

    $document = Document::factory()->create([
        'company_id' => $buyerCompany->id,
        'documentable_type' => $rfq->getMorphClass(),
        'documentable_id' => $rfq->id,
        'kind' => DocumentKind::Quote->value,
        'category' => 'commercial',
    ]);

    actingAs($supplierUser);

    $payload = [
        'supplier_id' => $supplier->id,
        'currency' => 'USD',
        'unit_price' => 99.00,
        'lead_time_days' => 12,
        'items' => $items->map(fn (RfqItem $item) => [
            'rfq_item_id' => $item->id,
            'unit_price' => 99.00,
            'lead_time_days' => 12,
        ])->toArray(),
        'attachments' => [$document->id],
    ];

    $response = $this->postJson("/api/rfqs/{$rfq->id}/quotes", $payload);

    $response->assertCreated();

    $quote = Quote::firstOrFail();

    $document->refresh();

    expect($document->documentable_type)->toBe($quote->getMorphClass())
        ->and((int) $document->documentable_id)->toBe($quote->getKey())
        ->and($document->company_id)->toBe($buyerCompany->id)
        ->and($document->meta['quote_id'] ?? null)->toBe($quote->getKey())
        ->and($document->meta['rfq_id'] ?? null)->toBe($rfq->getKey())
        ->and($document->meta['context'] ?? null)->toBe('quote_attachment');

    $quote->load('documents');

    expect($quote->documents)->toHaveCount(1)
        ->and($quote->attachments_count)->toBe(1);

    $rfq->refresh();

    expect($rfq->attachments_count)->toBe(0);
});

it('forbids quote submission when the supplier company is not approved even if invited', function (): void {
    Storage::fake('public');

    $plan = Plan::factory()->create([
        'code' => 'starter',
    ]);

    $buyerCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::None->value,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'rfqs_monthly_used' => 0,
        'storage_used_mb' => 0,
    ]);

    $buyerCustomer = Customer::factory()->create([
        'company_id' => $buyerCompany->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $buyerCompany->id,
        'customer_id' => $buyerCustomer->id,
        'stripe_status' => 'active',
    ]);

    $buyerUser = User::factory()->create([
        'role' => 'buyer_admin',
        'company_id' => $buyerCompany->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $buyerCompany->id,
        'user_id' => $buyerUser->id,
        'role' => $buyerUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplierCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::Pending->value,
        'is_verified' => false,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'rfqs_monthly_used' => 0,
        'storage_used_mb' => 0,
    ]);

    $supplierCustomer = Customer::factory()->create([
        'company_id' => $supplierCompany->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $supplierCompany->id,
        'customer_id' => $supplierCustomer->id,
        'stripe_status' => 'active',
    ]);

    $supplierUser = User::factory()->create([
        'role' => 'supplier_admin',
        'company_id' => $supplierCompany->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $supplierCompany->id,
        'user_id' => $supplierUser->id,
        'role' => $supplierUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplier = Supplier::factory()
        ->for($supplierCompany)
        ->create([
            'status' => 'approved',
        ]);

    $rfq = RFQ::factory()
        ->for($buyerCompany)
        ->create([
            'status' => 'open',
            'is_open_bidding' => false,
            'created_by' => $buyerUser->id,
        ]);

    $invitation = RfqInvitation::create([
        'company_id' => $buyerCompany->id,
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'invited_by' => $buyerUser->id,
        'status' => RfqInvitation::STATUS_PENDING,
    ]);

    $items = RfqItem::factory()->count(1)->create([
        'rfq_id' => $rfq->id,
    ]);

    actingAs($supplierUser);

    $payload = [
        'supplier_id' => $supplier->id,
        'currency' => 'USD',
        'unit_price' => 120.00,
        'lead_time_days' => 14,
        'min_order_qty' => 5,
        'items' => $items->map(fn (RfqItem $item) => [
            'rfq_item_id' => $item->id,
            'unit_price' => 60.00,
            'lead_time_days' => 14,
        ])->toArray(),
    ];

    $response = $this->postJson("/api/rfqs/{$rfq->id}/quotes", $payload);

    $response->assertForbidden();

    expect(Quote::count())->toBe(0)
        ->and(Document::count())->toBe(0)
        ->and(RfqInvitation::count())->toBe(1);
});

it('requires an invitation when rfq is not open bidding', function (): void {
    Storage::fake('public');

    $plan = Plan::factory()->create([
        'code' => 'starter',
    ]);

    $buyerCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::None->value,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $buyerCustomer = Customer::factory()->create([
        'company_id' => $buyerCompany->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $buyerCompany->id,
        'customer_id' => $buyerCustomer->id,
        'stripe_status' => 'active',
    ]);

    $buyerUser = User::factory()->create([
        'role' => 'buyer_admin',
        'company_id' => $buyerCompany->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $buyerCompany->id,
        'user_id' => $buyerUser->id,
        'role' => $buyerUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplierCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'is_verified' => true,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $supplierCustomer = Customer::factory()->create([
        'company_id' => $supplierCompany->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $supplierCompany->id,
        'customer_id' => $supplierCustomer->id,
        'stripe_status' => 'active',
    ]);

    $supplierUser = User::factory()->create([
        'role' => 'supplier_admin',
        'company_id' => $supplierCompany->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $supplierCompany->id,
        'user_id' => $supplierUser->id,
        'role' => $supplierUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplier = Supplier::factory()
        ->for($supplierCompany)
        ->create([
            'status' => 'approved',
        ]);

    $rfq = RFQ::factory()
        ->for($buyerCompany)
        ->create([
            'status' => 'open',
            'is_open_bidding' => false,
            'created_by' => $buyerUser->id,
        ]);

    $items = RfqItem::factory()->count(1)->create([
        'rfq_id' => $rfq->id,
    ]);

    actingAs($supplierUser);

    $payload = [
        'supplier_id' => $supplier->id,
        'currency' => 'USD',
        'unit_price' => 210.00,
        'lead_time_days' => 20,
        'min_order_qty' => 10,
        'items' => $items->map(fn (RfqItem $item) => [
            'rfq_item_id' => $item->id,
            'unit_price' => 105.00,
            'lead_time_days' => 20,
        ])->toArray(),
    ];

    $response = $this->postJson("/api/rfqs/{$rfq->id}/quotes", $payload);

    $response->assertForbidden();

    expect(Quote::count())->toBe(0)
        ->and(Document::count())->toBe(0)
        ->and(RfqInvitation::count())->toBe(0);
});

it('blocks supplier users without sourcing permission from submitting quotes', function (): void {
    Storage::fake('public');

    $plan = Plan::factory()->create([
        'code' => 'starter',
    ]);

    $buyerCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::None->value,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $buyerCustomer = Customer::factory()->create([
        'company_id' => $buyerCompany->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $buyerCompany->id,
        'customer_id' => $buyerCustomer->id,
        'stripe_status' => 'active',
    ]);

    $buyerUser = User::factory()->create([
        'role' => 'buyer_admin',
        'company_id' => $buyerCompany->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $buyerCompany->id,
        'user_id' => $buyerUser->id,
        'role' => $buyerUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplierCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'is_verified' => true,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $supplierCustomer = Customer::factory()->create([
        'company_id' => $supplierCompany->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $supplierCompany->id,
        'customer_id' => $supplierCustomer->id,
        'stripe_status' => 'active',
    ]);

    $supplierUser = User::factory()->create([
        'role' => 'finance',
        'company_id' => $supplierCompany->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $supplierCompany->id,
        'user_id' => $supplierUser->id,
        'role' => $supplierUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplier = Supplier::factory()
        ->for($supplierCompany)
        ->create([
            'status' => 'approved',
        ]);

    $rfq = RFQ::factory()
        ->for($buyerCompany)
        ->create([
            'status' => 'open',
            'is_open_bidding' => true,
            'created_by' => $buyerUser->id,
        ]);

    $items = RfqItem::factory()->count(1)->create([
        'rfq_id' => $rfq->id,
    ]);

    actingAs($supplierUser);

    $payload = [
        'supplier_id' => $supplier->id,
        'currency' => 'USD',
        'unit_price' => 310.00,
        'lead_time_days' => 12,
        'items' => $items->map(fn (RfqItem $item) => [
            'rfq_item_id' => $item->id,
            'unit_price' => 155.00,
            'lead_time_days' => 12,
        ])->toArray(),
    ];

    $response = $this->postJson("/api/rfqs/{$rfq->id}/quotes", $payload);

    $response->assertForbidden();

    expect(Quote::count())->toBe(0);
});

it('prevents quote drafts when the rfq deadline has passed', function (): void {
    Storage::fake('public');

    $plan = Plan::factory()->create([
        'code' => 'starter',
    ]);

    $buyerCompany = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $supplierCompany = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    $supplierCustomer = Customer::factory()->create([
        'company_id' => $supplierCompany->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $supplierCompany->id,
        'customer_id' => $supplierCustomer->id,
        'stripe_status' => 'active',
    ]);

    $supplierUser = User::factory()->create([
        'role' => 'supplier_admin',
        'company_id' => $supplierCompany->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $supplierCompany->id,
        'user_id' => $supplierUser->id,
        'role' => $supplierUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplier = Supplier::factory()
        ->for($supplierCompany)
        ->create([
            'status' => 'approved',
        ]);

    $rfq = RFQ::factory()
        ->for($buyerCompany)
        ->create([
            'status' => RFQ::STATUS_OPEN,
            'is_open_bidding' => true,
            'due_at' => now()->subDay(),
        ]);

    $items = RfqItem::factory()->count(1)->create([
        'rfq_id' => $rfq->id,
    ]);

    actingAs($supplierUser);

    $payload = [
        'supplier_id' => $supplier->id,
        'currency' => 'USD',
        'unit_price' => 120.00,
        'lead_time_days' => 10,
        'items' => $items->map(fn (RfqItem $item) => [
            'rfq_item_id' => $item->id,
            'unit_price' => 60.00,
            'lead_time_days' => 10,
        ])->toArray(),
    ];

    $response = $this->postJson("/api/rfqs/{$rfq->id}/quotes", $payload);

    $response->assertStatus(409)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('errors.rfq.0', fn ($value) => str_contains($value, 'deadline passed'));

    expect(Quote::count())->toBe(0);
});
