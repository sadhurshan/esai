<?php

use App\Enums\CompanySupplierStatus;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('allows buyer admins to invite suppliers to an RFQ', function (): void {
    $buyerCompany = createSubscribedCompany();
    $user = User::factory()->create([
        'company_id' => $buyerCompany->id,
        'role' => 'buyer_admin',
    ]);

    $rfq = RFQ::factory()->create([
        'company_id' => $buyerCompany->id,
    ]);

    $supplierCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved,
    ]);

    $supplier = Supplier::factory()
        ->for($supplierCompany)
        ->create([
            'status' => 'approved',
        ]);

    actingAs($user);

    postJson("/api/rfqs/{$rfq->getKey()}/invitations", [
        'supplier_ids' => [$supplier->getKey()],
    ])->assertOk()->assertJsonPath('status', 'success');
});

it('rejects buyer members when inviting suppliers to an RFQ', function (): void {
    $buyerCompany = createSubscribedCompany();
    $user = User::factory()->create([
        'company_id' => $buyerCompany->id,
        'role' => 'buyer_member',
    ]);

    $rfq = RFQ::factory()->create([
        'company_id' => $buyerCompany->id,
    ]);

    $supplierCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved,
    ]);

    $supplier = Supplier::factory()
        ->for($supplierCompany)
        ->create([
            'status' => 'approved',
        ]);

    actingAs($user);

    postJson("/api/rfqs/{$rfq->getKey()}/invitations", [
        'supplier_ids' => [$supplier->getKey()],
    ])->assertStatus(403)->assertJsonPath('status', 'error');
});

it('rejects users from other companies when inviting suppliers to an RFQ', function (): void {
    $buyerCompany = createSubscribedCompany();
    $otherCompany = createSubscribedCompany();

    $user = User::factory()->create([
        'company_id' => $otherCompany->id,
        'role' => 'buyer_admin',
    ]);

    $rfq = RFQ::factory()->create([
        'company_id' => $buyerCompany->id,
    ]);

    $supplierCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved,
    ]);

    $supplier = Supplier::factory()
        ->for($supplierCompany)
        ->create([
            'status' => 'approved',
        ]);

    actingAs($user);

    postJson("/api/rfqs/{$rfq->getKey()}/invitations", [
        'supplier_ids' => [$supplier->getKey()],
    ])->assertStatus(403)->assertJsonPath('status', 'error');
});

it('allows buyer admins to view RFQ invitations', function (): void {
    $buyerCompany = createSubscribedCompany();
    $user = User::factory()->create([
        'company_id' => $buyerCompany->id,
        'role' => 'buyer_admin',
    ]);

    $rfq = RFQ::factory()->create([
        'company_id' => $buyerCompany->id,
    ]);

    actingAs($user);

    getJson("/api/rfqs/{$rfq->getKey()}/invitations")
        ->assertOk()
        ->assertJsonPath('status', 'success');
});

it('rejects buyer members when viewing RFQ invitations', function (): void {
    $buyerCompany = createSubscribedCompany();
    $user = User::factory()->create([
        'company_id' => $buyerCompany->id,
        'role' => 'buyer_member',
    ]);

    $rfq = RFQ::factory()->create([
        'company_id' => $buyerCompany->id,
    ]);

    actingAs($user);

    getJson("/api/rfqs/{$rfq->getKey()}/invitations")
        ->assertStatus(403)
        ->assertJsonPath('status', 'error');
});

it('rejects other companies when viewing RFQ invitations', function (): void {
    $buyerCompany = createSubscribedCompany();
    $otherCompany = createSubscribedCompany();

    $user = User::factory()->create([
        'company_id' => $otherCompany->id,
        'role' => 'buyer_admin',
    ]);

    $rfq = RFQ::factory()->create([
        'company_id' => $buyerCompany->id,
    ]);

    actingAs($user);

    getJson("/api/rfqs/{$rfq->getKey()}/invitations")
        ->assertStatus(403)
        ->assertJsonPath('status', 'error');
});
