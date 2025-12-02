<?php

use App\Enums\CompanySupplierStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function provisionCompanyUser(string $role): array
{
    $plan = Plan::factory()->create([
        'code' => 'community',
        'price_usd' => 0,
        'rfqs_per_month' => 25,
        'invoices_per_month' => 25,
        'users_max' => 10,
        'storage_gb' => 5,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => $role,
    ]);

    return [$company, $user];
}

function provisionSupplierUser(string $role): array
{
    $company = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved,
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => $role,
    ]);

    return [$company, $user];
}

test('users without orders permission cannot access buyer order listings', function (): void {
    [$company, $user] = provisionCompanyUser('supplier_estimator');

    PurchaseOrder::factory()->create([
        'company_id' => $company->id,
    ]);

    actingAs($user);

    getJson('/api/buyer/orders')
        ->assertForbidden()
        ->assertJsonPath('message', 'Orders access required.');
});

test('buyer admin can access buyer order listings', function (): void {
    [$company, $user] = provisionCompanyUser('buyer_admin');

    PurchaseOrder::factory()->create([
        'company_id' => $company->id,
    ]);

    actingAs($user);

    getJson('/api/buyer/orders')
        ->assertOk()
        ->assertJsonPath('status', 'success');
});

test('supplier estimator cannot list supplier orders', function (): void {
    [$supplierCompany, $user] = provisionSupplierUser('supplier_estimator');

    actingAs($user);

    getJson('/api/supplier/orders')
        ->assertForbidden()
        ->assertJsonPath('message', 'Orders access required.');
});

test('supplier admin can list supplier orders', function (): void {
    [$supplierCompany, $user] = provisionSupplierUser('supplier_admin');

    $buyerCompany = Company::factory()->create();

    $supplier = Supplier::factory()->for($supplierCompany, 'company')->create([
        'company_id' => $supplierCompany->id,
        'status' => 'approved',
    ]);

    PurchaseOrder::factory()->for($buyerCompany, 'company')->create([
        'supplier_id' => $supplier->id,
    ]);

    actingAs($user);

    getJson('/api/supplier/orders')
        ->assertOk()
        ->assertJsonPath('status', 'success');
});

test('users without orders write permission cannot acknowledge orders', function (): void {
    [$supplierCompany, $user] = provisionSupplierUser('buyer_member');

    actingAs($user);

    postJson('/api/supplier/orders/1/ack', ['decision' => 'accept'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Orders write access required.');
});
