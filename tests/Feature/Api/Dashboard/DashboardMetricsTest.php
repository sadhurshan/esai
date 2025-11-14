<?php

use App\Enums\ReorderStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\ReorderSuggestion;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;

use function Pest\Laravel\actingAs;

it('returns aggregated dashboard metrics for the authenticated company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $supplier = Supplier::factory()->create(['company_id' => $company->id]);

    $openRfq = RFQ::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'status' => 'open',
    ]);

    Quote::query()->create([
        'company_id' => $company->id,
        'rfq_id' => $openRfq->id,
        'supplier_id' => $supplier->id,
        'submitted_by' => $user->id,
        'currency' => 'USD',
        'unit_price' => 125.50,
        'min_order_qty' => 10,
        'lead_time_days' => 14,
        'note' => null,
        'status' => 'submitted',
        'revision_no' => 1,
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'rfq_id' => $openRfq->id,
        'supplier_id' => $supplier->id,
        'status' => 'sent',
    ]);

    Invoice::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'supplier_id' => $supplier->id,
        'status' => 'pending',
    ]);

    $part = Part::factory()->create(['company_id' => $company->id]);
    $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

    ReorderSuggestion::factory()->create([
        'company_id' => $company->id,
        'part_id' => $part->id,
        'warehouse_id' => $warehouse->id,
        'status' => ReorderStatus::Open,
    ]);

    $otherCompany = Company::factory()->create();
    $otherSupplier = Supplier::factory()->create(['company_id' => $otherCompany->id]);
    $otherRfq = RFQ::factory()->create([
        'company_id' => $otherCompany->id,
        'status' => 'open',
    ]);

    Quote::query()->create([
        'company_id' => $otherCompany->id,
        'rfq_id' => $otherRfq->id,
        'supplier_id' => $otherSupplier->id,
        'unit_price' => 210.00,
        'lead_time_days' => 21,
        'status' => 'submitted',
        'revision_no' => 1,
    ]);

    $otherPurchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $otherCompany->id,
        'rfq_id' => $otherRfq->id,
        'supplier_id' => $otherSupplier->id,
        'status' => 'sent',
    ]);

    Invoice::factory()->create([
        'company_id' => $otherCompany->id,
        'purchase_order_id' => $otherPurchaseOrder->id,
        'supplier_id' => $otherSupplier->id,
        'status' => 'pending',
    ]);

    $otherPart = Part::factory()->create(['company_id' => $otherCompany->id]);
    $otherWarehouse = Warehouse::factory()->create(['company_id' => $otherCompany->id]);

    ReorderSuggestion::factory()->create([
        'company_id' => $otherCompany->id,
        'part_id' => $otherPart->id,
        'warehouse_id' => $otherWarehouse->id,
        'status' => ReorderStatus::Open,
    ]);

    actingAs($user);

    $this->getJson('/api/dashboard/metrics')
        ->assertOk()
        ->assertJson([
            'status' => 'success',
            'data' => [
                'open_rfq_count' => 1,
                'quotes_awaiting_review_count' => 1,
                'pos_awaiting_acknowledgement_count' => 1,
                'unpaid_invoice_count' => 1,
                'low_stock_part_count' => 1,
            ],
        ]);
});

it('enforces analytics plan access for dashboard metrics', function () {
    $plan = Plan::factory()->create(['analytics_enabled' => false]);
    $company = Company::factory()->for($plan)->create();
    $user = User::factory()->create(['company_id' => $company->id]);

    actingAs($user);

    $this->getJson('/api/dashboard/metrics')
        ->assertStatus(402)
        ->assertJson([
            'status' => 'error',
        ]);
});
