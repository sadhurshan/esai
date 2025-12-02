<?php

use App\Enums\CompanySupplierStatus;
use App\Models\Company;
use App\Models\RFQ;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function rfqInvitationTestContext(): array {
    $company = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved,
    ]);

    $buyerAdmin = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    $rfq = RFQ::factory()->for($company)->create([
        'company_id' => $company->id,
        'created_by' => $buyerAdmin->id,
    ]);

    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
        'status' => 'approved',
    ]);

    return compact('company', 'buyerAdmin', 'rfq', 'supplier');
}

it('allows buyer admins to invite approved suppliers and list invitations', function (): void {
    ['buyerAdmin' => $buyerAdmin, 'rfq' => $rfq, 'supplier' => $supplier] = rfqInvitationTestContext();

    $this->actingAs($buyerAdmin);

    $postResponse = $this->postJson("/api/rfqs/{$rfq->id}/invitations", [
        'supplier_ids' => [$supplier->id],
    ]);

    $postResponse->assertOk()
        ->assertJsonPath('message', 'Suppliers invited')
        ->assertJsonPath('data.items.0.supplier.id', (string) $supplier->id);

    $this->assertDatabaseHas('rfq_invitations', [
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'invited_by' => $buyerAdmin->id,
    ]);

    $indexResponse = $this->getJson("/api/rfqs/{$rfq->id}/invitations");

    $indexResponse->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.supplier.id', (string) $supplier->id);
});

it('blocks buyer admins from other companies from inviting suppliers to an rfq', function (): void {
    ['rfq' => $rfq, 'supplier' => $supplier] = rfqInvitationTestContext();

    $otherCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved,
    ]);

    $outsideBuyerAdmin = User::factory()->create([
        'company_id' => $otherCompany->id,
        'role' => 'buyer_admin',
    ]);

    $this->actingAs($outsideBuyerAdmin);

    $response = $this->postJson("/api/rfqs/{$rfq->id}/invitations", [
        'supplier_ids' => [$supplier->id],
    ]);

    $response->assertForbidden()
        ->assertJsonPath('message', 'RFQ invitations require sourcing write access.')
        ->assertJsonPath('errors.code', 'rfqs_write_required');

    expect(RfqInvitation::query()->count())->toBe(0);
});

it('blocks supplier-side roles from listing rfq invitations', function (): void {
    ['company' => $company, 'rfq' => $rfq] = rfqInvitationTestContext();

    $supplierUser = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'supplier_admin',
    ]);

    $this->actingAs($supplierUser);

    $response = $this->getJson("/api/rfqs/{$rfq->id}/invitations");

    $response->assertForbidden()
        ->assertJsonPath('message', 'RFQ invitations require sourcing write access.')
        ->assertJsonPath('errors.code', 'rfqs_write_required');
});
