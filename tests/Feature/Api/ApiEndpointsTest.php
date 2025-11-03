<?php

use App\Models\RFQ;
use App\Models\RFQQuote;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('creates an rfq with cad upload', function () {
    Storage::fake('local');

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

    $rfq = RFQ::factory()->create();
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
    $response = $this->postJson('/api/rfqs', []);

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Validation failed')
        ->assertJsonStructure(['errors' => ['item_name']]);
});

it('returns validation errors for invalid rfq quote payloads', function () {
    $rfq = RFQ::factory()->create();

    $response = $this->postJson("/api/rfqs/{$rfq->id}/quotes", []);

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Validation failed')
        ->assertJsonStructure(['errors' => ['supplier_id']]);
});
