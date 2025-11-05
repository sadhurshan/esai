<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
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

it('returns suppliers with envelope and pagination metadata', function () {
    Supplier::factory()->count(3)->create();

    $response = $this->getJson('/api/suppliers');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', null)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'items',
                'meta' => ['total', 'per_page', 'current_page', 'last_page'],
            ],
        ]);

    expect($response->json('data.meta.total'))->toBe(3);
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
    Storage::fake('local');
    actingAsSubscribedUser();

    $payload = [
        'item_name' => 'Gearbox Housing',
        'type' => 'manufacture',
        'quantity' => 120,
        'material' => 'Aluminum 7075-T6',
        'method' => 'CNC Milling',
        'client_company' => 'Elements Supply AI',
        'status' => 'awaiting',
        'deadline_at' => now()->addDays(14)->toDateString(),
        'sent_at' => now()->toDateString(),
        'is_open_bidding' => true,
        'notes' => 'Include inspection report.',
        'cad' => UploadedFile::fake()->create('housing.step', 50),
        'items' => [
            [
                'part_name' => 'Valve Body',
                'spec' => 'CNC machined, tolerance ±0.01mm',
                'quantity' => 60,
                'uom' => 'pcs',
                'target_price' => 125.50,
            ],
            [
                'part_name' => 'Cover Plate',
                'spec' => 'Anodized exterior',
                'quantity' => 60,
                'uom' => 'pcs',
                'target_price' => 48.20,
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
                'cad_path',
            ],
        ]);

    $rfq = RFQ::with('items')->first();
    expect($rfq)->not->toBeNull()
        ->and($rfq->items)->toHaveCount(2);

    Storage::disk('local')->assertExists($rfq->cad_path);
});

it('creates a quote with items and attachment upload', function () {
    Storage::fake('public');

    $user = actingAsSubscribedUser();

    $rfq = RFQ::factory()->for($user->company)->create([
        'is_open_bidding' => true,
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
        ->assertJsonStructure(['errors' => ['item_name']]);
});

it('returns validation errors for invalid quote payloads', function () {
    $user = actingAsSubscribedUser();

    $rfq = RFQ::factory()->for($user->company)->create([
        'is_open_bidding' => true,
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
