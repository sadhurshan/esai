<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\RFQ;
use App\Models\RFQQuote;
use App\Models\Subscription;
use App\Models\Supplier;
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

    $rfq = RFQ::first();
    expect($rfq)->not->toBeNull();

    Storage::disk('local')->assertExists($rfq->cad_path);
});

it('creates an rfq quote with attachment upload', function () {
    Storage::fake('local');

    $user = actingAsSubscribedUser();

    $rfq = RFQ::factory()->for($user->company)->create();
    $supplier = Supplier::factory()->create();

    $payload = [
        'supplier_id' => $supplier->id,
        'unit_price_usd' => 145.50,
        'lead_time_days' => 21,
        'note' => 'Includes expedited shipping.',
        'via' => 'direct',
        'attachment' => UploadedFile::fake()->create('quote.pdf', 80),
    ];

    $response = $this->postJson("/api/rfqs/{$rfq->id}/quotes", $payload);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure([
            'data' => [
                'id',
                'attachment_path',
            ],
        ]);

    $quote = RFQQuote::first();
    expect($quote)->not->toBeNull();
    Storage::disk('local')->assertExists($quote->attachment_path);
});

it('returns validation errors for invalid rfq payloads', function () {
    actingAsSubscribedUser();

    $response = $this->postJson('/api/rfqs', []);

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Validation failed')
        ->assertJsonStructure(['errors' => ['item_name']]);
});

it('returns validation errors for invalid rfq quote payloads', function () {
    $user = actingAsSubscribedUser();

    $rfq = RFQ::factory()->for($user->company)->create();

    $response = $this->postJson("/api/rfqs/{$rfq->id}/quotes", []);

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Validation failed')
        ->assertJsonStructure(['errors' => ['supplier_id']]);
});
