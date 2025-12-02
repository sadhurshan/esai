<?php

use App\Enums\CompanySupplierStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderShipment;
use App\Models\PurchaseOrderShipmentLine;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;
use function Pest\Laravel\actingAs;

it('returns buyer orders scoped to the users company', function (): void {
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
            'po_number' => 'PO-TEST-001',
            'status' => 'sent',
            'sent_at' => Carbon::now()->subDay(),
            'ack_status' => 'sent',
            'currency' => 'USD',
            'subtotal_minor' => 12500,
            'total_minor' => 15000,
        ]);

    PurchaseOrderLine::factory()
        ->for($order, 'purchaseOrder')
        ->create([
            'line_no' => 1,
            'description' => 'Machined Block',
            'quantity' => 5,
            'unit_price' => 250.50,
        ]);

    // noise order that should be filtered out
    PurchaseOrder::factory()->create();

    actingAs($buyerUser);

    $response = $this->getJson('/api/buyer/orders');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.soNumber', 'PO-TEST-001')
        ->assertJsonPath('data.items.0.buyerCompanyId', $buyerCompany->id);
});

it('returns buyer order details when scoped to the company', function (): void {
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
            'po_number' => 'PO-TEST-DETAIL',
            'status' => 'sent',
            'sent_at' => Carbon::now()->subDay(),
            'ack_status' => 'sent',
            'currency' => 'USD',
        ]);

    PurchaseOrderLine::factory()
        ->for($order, 'purchaseOrder')
        ->count(2)
        ->sequence(
            ['line_no' => 1, 'description' => 'Gear', 'quantity' => 3, 'unit_price' => 120.00],
            ['line_no' => 2, 'description' => 'Shaft', 'quantity' => 2, 'unit_price' => 80.00],
        )
        ->create();

    actingAs($buyerUser);

    $response = $this->getJson(sprintf('/api/buyer/orders/%d', $order->id));

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.soNumber', 'PO-TEST-DETAIL')
        ->assertJsonCount(2, 'data.lines');
});

it('returns supplier orders scoped to approved supplier companies', function (): void {
    $supplierCompany = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved,
    ]);

    $supplierUser = User::factory()->for($supplierCompany, 'company')->create([
        'role' => 'supplier_admin',
    ]);

    $buyerCompany = Company::factory()->create();

    $supplier = Supplier::factory()
        ->for($supplierCompany, 'company')
        ->create([
            'company_id' => $supplierCompany->id,
            'status' => 'approved',
        ]);

    $visibleOrder = PurchaseOrder::factory()
        ->for($buyerCompany, 'company')
        ->create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-SUP-001',
            'status' => 'sent',
            'sent_at' => Carbon::now()->subDays(2),
            'ack_status' => 'sent',
        ]);

    // order tied to another supplier should not be returned
    PurchaseOrder::factory()->create();

    PurchaseOrderLine::factory()->for($visibleOrder, 'purchaseOrder')->create();

    actingAs($supplierUser);

    $response = $this->getJson('/api/supplier/orders');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.soNumber', 'PO-SUP-001');
});

it('allows suppliers to acknowledge orders via the API', function (): void {
    $supplierCompany = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved,
    ]);

    $supplierUser = User::factory()->for($supplierCompany, 'company')->create([
        'role' => 'supplier_admin',
    ]);

    $supplier = Supplier::factory()
        ->for($supplierCompany, 'company')
        ->create([
            'company_id' => $supplierCompany->id,
            'status' => 'approved',
        ]);

    $buyerCompany = Company::factory()->create();
    $rfq = RFQ::factory()->create();

    $quote = Quote::query()->create([
        'company_id' => $buyerCompany->id,
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'submitted_by' => $supplierUser->id,
        'currency' => 'USD',
        'unit_price' => 100,
        'min_order_qty' => 1,
        'lead_time_days' => 10,
        'note' => null,
        'status' => 'submitted',
        'revision_no' => 1,
    ]);

    $order = PurchaseOrder::factory()
        ->for($buyerCompany, 'company')
        ->create([
            'quote_id' => $quote->id,
            'supplier_id' => $supplier->id,
            'status' => 'sent',
            'ack_status' => 'sent',
            'sent_at' => Carbon::now()->subHour(),
        ]);

    $salesOrder = Order::query()->where('purchase_order_id', $order->id)->firstOrFail();

    actingAs($supplierUser);

    $response = $this->postJson(sprintf('/api/supplier/orders/%d/ack', $salesOrder->id), [
        'decision' => 'accept',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    expect($order->fresh()->ack_status)->toBe('acknowledged');
});

it('requires decline reasons to be persisted on acknowledgement', function (): void {
    $supplierCompany = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved,
    ]);

    $supplierUser = User::factory()->for($supplierCompany, 'company')->create([
        'role' => 'supplier_admin',
    ]);

    $supplier = Supplier::factory()
        ->for($supplierCompany, 'company')
        ->create([
            'company_id' => $supplierCompany->id,
            'status' => 'approved',
        ]);

    $buyerCompany = Company::factory()->create();
    $rfq = RFQ::factory()->create();

    $quote = Quote::query()->create([
        'company_id' => $buyerCompany->id,
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'submitted_by' => $supplierUser->id,
        'currency' => 'USD',
        'unit_price' => 150,
        'min_order_qty' => 1,
        'lead_time_days' => 12,
        'note' => null,
        'status' => 'submitted',
        'revision_no' => 1,
    ]);

    $order = PurchaseOrder::factory()
        ->for($buyerCompany, 'company')
        ->create([
            'quote_id' => $quote->id,
            'supplier_id' => $supplier->id,
            'status' => 'sent',
            'ack_status' => 'sent',
            'sent_at' => Carbon::now()->subHour(),
        ]);

    $salesOrder = Order::query()->where('purchase_order_id', $order->id)->firstOrFail();

    actingAs($supplierUser);

    $response = $this->postJson(sprintf('/api/supplier/orders/%d/ack', $salesOrder->id), [
        'decision' => 'decline',
        'reason' => 'Capacity constraints',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.acknowledgements.0.reason', 'Capacity constraints');

    $order->refresh();

    expect($order->ack_status)->toBe('declined');
    expect($order->ack_reason)->toBe('Capacity constraints');
});

it('allows suppliers to create shipments for their orders', function (): void {
    $supplierCompany = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved,
    ]);

    $supplierUser = User::factory()->for($supplierCompany, 'company')->create([
        'role' => 'supplier_admin',
    ]);

    $supplier = Supplier::factory()
        ->for($supplierCompany, 'company')
        ->create([
            'company_id' => $supplierCompany->id,
            'status' => 'approved',
        ]);

    $buyerCompany = Company::factory()->create();

    $order = PurchaseOrder::factory()
        ->for($buyerCompany, 'company')
        ->create([
            'supplier_id' => $supplier->id,
            'status' => 'acknowledged',
            'ack_status' => 'acknowledged',
            'po_number' => 'PO-SHIP-001',
        ]);

    $line = PurchaseOrderLine::factory()
        ->for($order, 'purchaseOrder')
        ->create([
            'line_no' => 1,
            'quantity' => 10,
        ]);

    $salesOrder = Order::query()->where('purchase_order_id', $order->id)->firstOrFail();

    actingAs($supplierUser);

    $response = $this->postJson(sprintf('/api/supplier/orders/%d/shipments', $salesOrder->id), [
        'carrier' => 'FedEx',
        'tracking_number' => 'TRK-123',
        'shipped_at' => Carbon::now()->toIso8601String(),
        'lines' => [
            ['so_line_id' => $line->id, 'qty_shipped' => 4],
        ],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.shipments.0.carrier', 'FedEx')
        ->assertJsonPath('data.shipments.0.lines.0.qtyShipped', 4)
        ->assertJsonPath('data.lines.0.qtyShipped', 4);

    $order->refresh();

    expect($order->shipments)->toHaveCount(1);
});

it('prevents shipment quantities beyond the remaining amounts', function (): void {
    $supplierCompany = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved,
    ]);

    $supplierUser = User::factory()->for($supplierCompany, 'company')->create([
        'role' => 'supplier_admin',
    ]);

    $supplier = Supplier::factory()
        ->for($supplierCompany, 'company')
        ->create([
            'company_id' => $supplierCompany->id,
            'status' => 'approved',
        ]);

    $buyerCompany = Company::factory()->create();

    $order = PurchaseOrder::factory()
        ->for($buyerCompany, 'company')
        ->create([
            'supplier_id' => $supplier->id,
            'status' => 'acknowledged',
            'ack_status' => 'acknowledged',
            'po_number' => 'PO-SHIP-002',
        ]);

    $line = PurchaseOrderLine::factory()
        ->for($order, 'purchaseOrder')
        ->create([
            'line_no' => 1,
            'quantity' => 3,
        ]);

    $salesOrder = Order::query()->where('purchase_order_id', $order->id)->firstOrFail();

    actingAs($supplierUser);

    $response = $this->postJson(sprintf('/api/supplier/orders/%d/shipments', $salesOrder->id), [
        'carrier' => 'DHL',
        'tracking_number' => 'TRK-999',
        'shipped_at' => Carbon::now()->toIso8601String(),
        'lines' => [
            ['so_line_id' => $line->id, 'qty_shipped' => 5],
        ],
    ]);

    $response
        ->assertStatus(422)
        ->assertJson(fn (AssertableJson $json): AssertableJson => $json
            ->has('errors.lines.0')
            ->etc());
});

it('allows suppliers to update shipment status', function (): void {
    $supplierCompany = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved,
    ]);

    $supplierUser = User::factory()->for($supplierCompany, 'company')->create([
        'role' => 'supplier_admin',
    ]);

    $supplier = Supplier::factory()
        ->for($supplierCompany, 'company')
        ->create([
            'company_id' => $supplierCompany->id,
            'status' => 'approved',
        ]);

    $buyerCompany = Company::factory()->create();

    $order = PurchaseOrder::factory()
        ->for($buyerCompany, 'company')
        ->create([
            'supplier_id' => $supplier->id,
            'status' => 'acknowledged',
            'ack_status' => 'acknowledged',
            'po_number' => 'PO-SHIP-003',
        ]);

    $line = PurchaseOrderLine::factory()
        ->for($order, 'purchaseOrder')
        ->create([
            'line_no' => 1,
            'quantity' => 2,
        ]);

    $shipment = PurchaseOrderShipment::factory()
        ->for($order, 'purchaseOrder')
        ->create([
            'company_id' => $buyerCompany->id,
            'supplier_company_id' => $supplierCompany->id,
            'status' => 'pending',
            'carrier' => 'DHL',
            'tracking_number' => 'SHIP-456',
        ]);

    PurchaseOrderShipmentLine::factory()
        ->for($shipment, 'shipment')
        ->for($line, 'purchaseOrderLine')
        ->create([
            'qty_shipped' => 2,
        ]);

    actingAs($supplierUser);

    $response = $this->postJson(sprintf('/api/supplier/shipments/%d/status', $shipment->id), [
        'status' => 'delivered',
        'delivered_at' => Carbon::now()->toIso8601String(),
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.shipments.0.status', 'delivered');

    expect($shipment->fresh()->status)->toBe('delivered');
});
