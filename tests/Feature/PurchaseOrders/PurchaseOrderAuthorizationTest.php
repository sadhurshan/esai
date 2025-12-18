<?php

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

test('buyer admin can send a purchase order', function (): void {
    $company = createSubscribedCompany();
    $supplier = Supplier::factory()->for($company)->create();
    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'status' => 'draft',
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    actingAs($user);

    postJson("/api/purchase-orders/{$purchaseOrder->getKey()}/send", [
        'message' => 'Please confirm receipt.',
    ])->assertOk();
});

test('buyer member cannot send a purchase order', function (): void {
    $company = createSubscribedCompany();
    $supplier = Supplier::factory()->for($company)->create();
    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'status' => 'draft',
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_member',
    ]);

    actingAs($user);

    postJson("/api/purchase-orders/{$purchaseOrder->getKey()}/send", [
        'message' => 'Please confirm receipt.',
    ])->assertStatus(403);
});

test('buyer member cannot cancel a purchase order', function (): void {
    $company = createSubscribedCompany();
    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'draft',
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_member',
    ]);

    actingAs($user);

    postJson("/api/purchase-orders/{$purchaseOrder->getKey()}/cancel")
        ->assertStatus(403);
});

test('buyer admin can initiate purchase orders from awards', function (): void {
    $company = createSubscribedCompany();
    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    actingAs($user);

    postJson('/api/pos/from-awards', [
        'award_ids' => [123],
    ])->assertStatus(422); // fails validation but confirms middleware allows buyer admins.
});

test('buyer member cannot initiate purchase orders from awards', function (): void {
    $company = createSubscribedCompany();
    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_member',
    ]);

    actingAs($user);

    postJson('/api/pos/from-awards', [
        'award_ids' => [123],
    ])->assertStatus(403)
        ->assertJsonPath('status', 'error');
});
