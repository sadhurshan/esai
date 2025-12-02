<?php

use App\Models\Company;
use App\Models\GoodsReceiptLine;
use App\Models\GoodsReceiptNote;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AnalyticsService;
use Carbon\Carbon;

use function Pest\Laravel\actingAs;

test('analytics overview returns enriched metadata for charts and kpis', function () {
    $plan = Plan::factory()->create([
        'analytics_enabled' => true,
        'analytics_history_months' => 6,
    ]);

    $company = Company::factory()->for($plan)->create();
    $user = User::factory()->create(['company_id' => $company->id]);

    $supplierA = Supplier::factory()->create();
    $supplierB = Supplier::factory()->create();

    $periodStart = Carbon::now()->startOfMonth();
    $periodEnd = Carbon::now()->endOfMonth();

    $rfq = RFQ::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'status' => 'open',
        'publish_at' => $periodStart->copy()->addDays(1),
        'open_bidding' => false,
        'is_open_bidding' => false,
    ]);

    RfqInvitation::query()->create([
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplierA->id,
        'invited_by' => $user->id,
        'status' => RfqInvitation::STATUS_PENDING,
    ]);

    Quote::query()->create([
        'company_id' => $company->id,
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplierA->id,
        'submitted_by' => $user->id,
        'currency' => 'USD',
        'unit_price' => 125.50,
        'min_order_qty' => 1,
        'lead_time_days' => 14,
        'status' => 'submitted',
        'revision_no' => 1,
        'submitted_at' => $periodStart->copy()->addDays(2),
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplierA->id,
        'status' => 'sent',
        'created_at' => $periodStart->copy()->addDays(5),
        'updated_at' => $periodStart->copy()->addDays(5),
    ]);

    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'quantity' => 10,
        'unit_price' => 250,
        'currency' => 'USD',
        'delivery_date' => $periodStart->copy()->addDays(12)->toDateString(),
    ]);

    $goodsReceipt = GoodsReceiptNote::query()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'number' => 'GRN-0001',
        'inspected_by_id' => $user->id,
        'inspected_at' => $periodStart->copy()->addDays(10),
        'status' => 'complete',
    ]);

    GoodsReceiptLine::query()->create([
        'goods_receipt_note_id' => $goodsReceipt->id,
        'purchase_order_line_id' => $poLine->id,
        'received_qty' => 10,
        'accepted_qty' => 10,
        'rejected_qty' => 0,
    ]);

    Invoice::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'supplier_id' => $supplierA->id,
        'status' => 'paid',
        'total' => 15000,
        'subtotal' => 15000,
        'tax_amount' => 0,
        'created_at' => $periodStart->copy()->addDays(6),
        'updated_at' => $periodStart->copy()->addDays(6),
    ]);

    Invoice::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'supplier_id' => $supplierB->id,
        'status' => 'pending',
        'total' => 6000,
        'subtotal' => 6000,
        'tax_amount' => 0,
        'created_at' => $periodStart->copy()->addDays(7),
        'updated_at' => $periodStart->copy()->addDays(7),
    ]);

    /** @var AnalyticsService $service */
    $service = app(AnalyticsService::class);
    $service->generateForPeriod($company, $periodStart, $periodEnd);

    actingAs($user);

    $response = $this->getJson('/api/analytics/overview')
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $data = $response->json('data');

    expect(data_get($data, 'response_rate.0.meta.rfq_count'))->toBe(1);
    expect(data_get($data, 'response_rate.0.meta.quotes_submitted'))->toBe(1);
    expect(data_get($data, 'spend.0.meta.top_suppliers'))->toHaveCount(2);
    expect(data_get($data, 'spend.0.meta.top_suppliers.0.total'))->toBeGreaterThan(0);
    expect(data_get($data, 'spend.0.meta.invoice_count'))->toBe(2);
    expect(data_get($data, 'otif.0.meta.on_time_lines'))->toBe(1);
    expect($response->json('meta.from'))->not->toBeNull();
    expect($response->json('meta.to'))->not->toBeNull();
});
