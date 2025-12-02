<?php

use App\Enums\CompanySupplierStatus;
use App\Enums\RfpStatus;
use App\Models\Document;
use App\Models\Rfp;
use App\Models\RfpProposal;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('allows suppliers to submit proposals with attachments', function (): void {
    Storage::fake(config('documents.disk'));

    $buyerCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    $supplierCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    $rfp = Rfp::factory()->create([
        'company_id' => $buyerCompany->id,
        'status' => RfpStatus::Published->value,
    ]);

    $supplierUser = User::factory()->create([
        'company_id' => $supplierCompany->id,
        'role' => 'supplier_admin',
    ]);

    $payload = [
        'supplier_company_id' => $supplierCompany->id,
        'price_total' => '125000.00',
        'currency' => 'usd',
        'lead_time_days' => 120,
        'approach_summary' => 'We will deploy a blended engineering + PM team.',
        'schedule_summary' => 'Mobilize in 2 weeks, complete by Q3.',
        'value_add_summary' => 'Included warranty extension.',
        'attachments' => [
            UploadedFile::fake()->create('proposal.pdf', 200, 'application/pdf'),
        ],
    ];

    $this->actingAs($supplierUser);

    $response = $this
        ->withHeader('Accept', 'application/json')
        ->post('/api/rfps/'.$rfp->id.'/proposals', $payload);

    $response
        ->assertCreated()
        ->assertJsonPath('data.rfp_id', $rfp->id)
        ->assertJsonPath('data.supplier_company_id', $supplierCompany->id)
        ->assertJsonPath('data.attachments_count', 1)
        ->assertJsonPath('data.attachments.0.filename', 'proposal.pdf');

    $this->assertDatabaseHas('rfp_proposals', [
        'rfp_id' => $rfp->id,
        'supplier_company_id' => $supplierCompany->id,
        'lead_time_days' => 120,
    ]);

    $documentCount = CompanyContext::forCompany($buyerCompany->id, fn () => Document::query()->count());

    expect($documentCount)->toBe(1);
});

it('rejects proposals for unpublished rfps', function (): void {
    $buyerCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    $supplierCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    $rfp = Rfp::factory()->create([
        'company_id' => $buyerCompany->id,
        'status' => RfpStatus::Draft->value,
    ]);

    $supplierUser = User::factory()->create([
        'company_id' => $supplierCompany->id,
        'role' => 'supplier_admin',
    ]);

    $this->actingAs($supplierUser);

    $response = $this
        ->withHeader('Accept', 'application/json')
        ->post('/api/rfps/'.$rfp->id.'/proposals', [
            'supplier_company_id' => $supplierCompany->id,
            'price_total' => '55000',
            'currency' => 'USD',
            'lead_time_days' => 30,
            'approach_summary' => 'Plan',
            'schedule_summary' => 'Schedule',
        ]);

    $response->assertUnprocessable()
        ->assertJsonPath('errors.rfp.0', 'Proposals can only be submitted to published RFPs.');

    expect($rfp->proposals()->count())->toBe(0);
});

it('blocks submissions when the supplier company lacks proposal permissions', function (): void {
    $buyerCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    $supplierCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    $rfp = Rfp::factory()->create([
        'company_id' => $buyerCompany->id,
        'status' => RfpStatus::Published->value,
    ]);

    $unauthorizedSupplier = User::factory()->create([
        'company_id' => $supplierCompany->id,
        'role' => 'finance',
    ]);

    $this->actingAs($unauthorizedSupplier);

    $response = $this
        ->withHeader('Accept', 'application/json')
        ->post('/api/rfps/'.$rfp->id.'/proposals', [
            'supplier_company_id' => $supplierCompany->id,
            'price_total' => '75000',
            'currency' => 'USD',
            'lead_time_days' => 45,
            'approach_summary' => 'Approach',
            'schedule_summary' => 'Schedule',
        ]);

    $response->assertForbidden()
        ->assertJsonPath('errors.code', 'rfp_proposal_submit_denied');

    expect($rfp->proposals()->count())->toBe(0);
});

it('allows buyer teams to review proposals for an rfp', function (): void {
    $buyerCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    $supplierA = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    $supplierB = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    $rfp = Rfp::factory()->create([
        'company_id' => $buyerCompany->id,
        'status' => RfpStatus::Published->value,
    ]);

    $buyerUser = User::factory()->create([
        'company_id' => $buyerCompany->id,
        'role' => 'buyer_admin',
    ]);

    RfpProposal::factory()->for($rfp)->create([
        'company_id' => $buyerCompany->id,
        'supplier_company_id' => $supplierA->id,
        'price_total_minor' => 15000000,
        'currency' => 'USD',
        'lead_time_days' => 90,
    ]);

    RfpProposal::factory()->for($rfp)->create([
        'company_id' => $buyerCompany->id,
        'supplier_company_id' => $supplierB->id,
        'price_total_minor' => 12000000,
        'currency' => 'USD',
        'lead_time_days' => 60,
    ]);

    $this->actingAs($buyerUser);

    $response = $this->getJson('/api/rfps/'.$rfp->id.'/proposals');

    $response->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.summary.total', 2)
        ->assertJsonPath('data.summary.min_price_minor', 12000000)
        ->assertJsonPath('data.items.0.rfp_id', $rfp->id);
});

it('blocks suppliers from reviewing proposal comparisons', function (): void {
    $buyerCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    $supplierCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    $rfp = Rfp::factory()->create([
        'company_id' => $buyerCompany->id,
        'status' => RfpStatus::Published->value,
    ]);

    User::factory()->create([
        'company_id' => $buyerCompany->id,
        'role' => 'buyer_admin',
    ]);

    $supplierUser = User::factory()->create([
        'company_id' => $supplierCompany->id,
        'role' => 'supplier_admin',
    ]);

    $this->actingAs($supplierUser);

    $response = $this->getJson('/api/rfps/'.$rfp->id.'/proposals');

    $response->assertForbidden()
        ->assertJsonPath('errors.code', 'rfp_proposal_review_denied');
});
