<?php

use App\Enums\CompanySupplierStatus;
use App\Models\Company;
use App\Models\Document;
use App\Models\Plan;
use App\Models\RFQ;
use App\Models\RFQQuote;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('returns rfq quotes with cursor metadata for buyers', function (): void {
    $plan = Plan::factory()->create();
    $buyerCompany = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'supplier_status' => CompanySupplierStatus::None->value,
    ]);

    $buyer = User::factory()->create([
        'company_id' => $buyerCompany->id,
        'role' => 'buyer_admin',
    ]);

    DB::table('company_user')->insert([
        'company_id' => $buyerCompany->id,
        'user_id' => $buyer->id,
        'role' => $buyer->role,
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rfq = RFQ::factory()->create([
        'company_id' => $buyerCompany->id,
        'created_by' => $buyer->id,
    ]);

    RFQQuote::factory()->count(3)->create([
        'company_id' => $buyerCompany->id,
        'rfq_id' => $rfq->id,
    ]);

    $this->actingAs($buyer);

    $response = $this->getJson("/api/rfq-quotes/{$rfq->id}");

    $response->assertOk()
        ->assertJsonPath('data.items.0.rfq_id', $rfq->id)
        ->assertJsonStructure([
            'data' => [
                'meta' => [
                    'next_cursor',
                    'prev_cursor',
                    'per_page',
                ],
            ],
            'meta' => [
                'cursor' => ['next_cursor', 'prev_cursor', 'has_next', 'has_prev'],
                'request_id',
            ],
        ]);
});

it('allows invited suppliers to list their rfq quotes', function (): void {
    $plan = Plan::factory()->create();

    $buyerCompany = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'supplier_status' => CompanySupplierStatus::None->value,
    ]);

    $supplierCompany = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    $supplierUser = User::factory()->create([
        'company_id' => $supplierCompany->id,
        'role' => 'supplier_admin',
    ]);

    DB::table('company_user')->insert([
        'company_id' => $supplierCompany->id,
        'user_id' => $supplierUser->id,
        'role' => $supplierUser->role,
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplier = Supplier::factory()->create([
        'company_id' => $supplierCompany->id,
        'status' => 'approved',
    ]);

    $rfq = RFQ::factory()->create([
        'company_id' => $buyerCompany->id,
        'created_by' => $supplierUser->id,
        'open_bidding' => false,
    ]);

    RfqInvitation::factory()->create([
        'company_id' => $buyerCompany->id,
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'invited_by' => $supplierUser->id,
    ]);

    RFQQuote::factory()->create([
        'company_id' => $buyerCompany->id,
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
    ]);

    $this->actingAs($supplierUser);

    $response = $this->getJson("/api/rfq-quotes/{$rfq->id}");

    $response->assertOk()
        ->assertJsonPath('data.items.0.supplier_id', $supplier->id);
});

it('allows invited suppliers to submit rfq quotes with attachments', function (): void {
    Storage::fake('local');
    config(['documents.disk' => 'local']);
    config(['security.scan_uploads' => false]);

    $plan = Plan::factory()->create();

    $buyerCompany = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'supplier_status' => CompanySupplierStatus::None->value,
    ]);

    $supplierCompany = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    $supplierUser = User::factory()->create([
        'company_id' => $supplierCompany->id,
        'role' => 'supplier_admin',
    ]);

    DB::table('company_user')->insert([
        'company_id' => $supplierCompany->id,
        'user_id' => $supplierUser->id,
        'role' => $supplierUser->role,
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplier = Supplier::factory()->create([
        'company_id' => $supplierCompany->id,
        'status' => 'approved',
    ]);

    $rfq = RFQ::factory()->create([
        'company_id' => $buyerCompany->id,
        'created_by' => $supplierUser->id,
        'open_bidding' => false,
    ]);

    RfqInvitation::factory()->create([
        'company_id' => $buyerCompany->id,
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'invited_by' => $supplierUser->id,
    ]);

    $this->actingAs($supplierUser);

    $payload = [
        'supplier_id' => $supplier->id,
        'unit_price_usd' => 150,
        'lead_time_days' => 10,
        'note' => 'Fastest possible delivery.',
        'via' => 'direct',
        'attachment' => UploadedFile::fake()->create('quote.pdf', 120, 'application/pdf'),
    ];

    $response = $this->postJson("/api/rfq-quotes/{$rfq->id}", $payload);

    $response->assertCreated()
        ->assertJsonPath('message', 'RFQ quote created')
        ->assertJsonPath('data.supplier_id', $supplier->id)
        ->assertJsonPath('data.rfq_id', $rfq->id);

    expect(RFQQuote::count())->toBe(1)
        ->and(Document::count())->toBe(1);

    $quote = RFQQuote::first();
    $document = Document::first();

    expect($quote->company_id)->toBe($buyerCompany->id)
        ->and($quote->attachment_path)->toBe($document->path)
        ->and($document->documentable_id)->toBe($quote->id);
});
