<?php

use App\Enums\CompanySupplierStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderShipment;
use App\Models\PurchaseOrderShipmentLine;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Carbon;
use function Pest\Laravel\actingAs;

it('returns shipments for buyer-owned purchase orders', function (): void {
    $plan = Plan::factory()->create([
        'code' => 'community',
        'price_usd' => 0,
    ]);

    $buyerCompany = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'status' => 'active',
    ]);

    $buyerUser = User::factory()->for($buyerCompany, 'company')->create([
        'role' => 'buyer_admin',
    ]);

    $supplierCompany = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved,
    ]);

    $supplier = Supplier::factory()
        ->for($supplierCompany, 'company')
        ->create([
            'company_id' => $supplierCompany->id,
            'status' => 'approved',
        ]);

    $order = PurchaseOrder::factory()
        ->for($buyerCompany, 'company')
        ->create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-SHIP-LIST',
            'status' => 'acknowledged',
            'ack_status' => 'acknowledged',
            'sent_at' => Carbon::now()->subDay(),
        ]);

    $line = PurchaseOrderLine::factory()
        ->for($order, 'purchaseOrder')
        ->create([
            'line_no' => 1,
            'quantity' => 25,
        ]);

    $shipmentOne = PurchaseOrderShipment::factory()
        ->for($order, 'purchaseOrder')
        ->create([
            'company_id' => $buyerCompany->id,
            'supplier_company_id' => $supplierCompany->id,
            'shipment_number' => 'PO-SHIP-LIST-001',
            'status' => 'pending',
            'shipped_at' => Carbon::now()->subDay(),
        ]);

    PurchaseOrderShipmentLine::factory()->create([
        'purchase_order_shipment_id' => $shipmentOne->id,
        'purchase_order_line_id' => $line->id,
        'qty_shipped' => 5,
    ]);

    $shipmentTwo = PurchaseOrderShipment::factory()
        ->for($order, 'purchaseOrder')
        ->create([
            'company_id' => $buyerCompany->id,
            'supplier_company_id' => $supplierCompany->id,
            'shipment_number' => 'PO-SHIP-LIST-002',
            'status' => 'delivered',
            'shipped_at' => Carbon::now()->subHours(6),
            'delivered_at' => Carbon::now(),
        ]);

    PurchaseOrderShipmentLine::factory()->create([
        'purchase_order_shipment_id' => $shipmentTwo->id,
        'purchase_order_line_id' => $line->id,
        'qty_shipped' => 10,
    ]);

    actingAs($buyerUser);

    $response = $this->getJson(sprintf('/api/purchase-orders/%d/shipments', $order->id));

    $response
        ->assertOk()
        ->assertJsonPath('data.purchaseOrderId', $order->id)
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.lines.0.qtyShipped', 10)
        ->assertJsonPath('data.items.1.lines.0.qtyShipped', 5);
});

it('prevents buyers from fetching shipments belonging to other companies', function (): void {
    $plan = Plan::factory()->create([
        'code' => 'community',
        'price_usd' => 0,
    ]);

    $buyerCompany = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'status' => 'active',
    ]);

    $otherCompany = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'status' => 'active',
    ]);

    $buyerUser = User::factory()->for($otherCompany, 'company')->create([
        'role' => 'buyer_admin',
    ]);

    $supplierCompany = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved,
    ]);

    $supplier = Supplier::factory()
        ->for($supplierCompany, 'company')
        ->create([
            'company_id' => $supplierCompany->id,
            'status' => 'approved',
        ]);

    $order = PurchaseOrder::factory()
        ->for($buyerCompany, 'company')
        ->create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-SHIP-LIST-SECURE',
            'status' => 'acknowledged',
            'ack_status' => 'acknowledged',
        ]);

    actingAs($buyerUser);

    $response = $this->getJson(sprintf('/api/purchase-orders/%d/shipments', $order->id));

    $response->assertStatus(404);
});
