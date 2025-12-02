<?php

use App\Enums\RiskGrade;
use App\Models\Company;
use App\Models\Customer;
use App\Models\GoodsReceiptLine;
use App\Models\GoodsReceiptNote;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\RfqInvitation;
use App\Models\RfqItem;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierRiskService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

it('calculates supplier risk metrics and assigns grades', function (): void {
    $periodStart = Carbon::create(2025, 4, 1, 0, 0, 0, 'UTC');
    Carbon::setTestNow($periodStart->copy()->addDays(12));

    $plan = Plan::factory()->create([
        'risk_scores_enabled' => true,
        'risk_history_months' => 12,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'stripe_status' => 'active',
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
        'status' => 'approved',
    ]);

    $rfq = RFQ::factory()->create([
        'company_id' => $company->id,
        'status' => 'awarded',
    ]);

    $rfqItem = RfqItem::factory()->create([
        'rfq_id' => $rfq->id,
        'target_price' => 100,
    ]);

    $quote = Quote::create([
        'company_id' => $company->id,
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'submitted_by' => $user->id,
        'currency' => 'USD',
        'unit_price' => 120,
        'min_order_qty' => 5,
        'lead_time_days' => 14,
        'status' => 'awarded',
        'revision_no' => 1,
    ]);

    $purchaseOrder = PurchaseOrder::create([
        'company_id' => $company->id,
        'rfq_id' => $rfq->id,
        'quote_id' => $quote->id,
        'po_number' => 'PO-'.Str::upper(Str::random(6)),
        'currency' => 'USD',
        'status' => 'sent',
    ]);

    $poLineEarly = PurchaseOrderLine::create([
        'purchase_order_id' => $purchaseOrder->id,
        'rfq_item_id' => $rfqItem->id,
        'line_no' => 1,
        'description' => 'Assembly',
        'quantity' => 10,
        'uom' => 'EA',
        'unit_price' => 120,
        'delivery_date' => $periodStart->copy()->addDays(7),
        'received_qty' => 10,
        'receiving_status' => 'received',
    ]);

    $poLineLate = PurchaseOrderLine::create([
        'purchase_order_id' => $purchaseOrder->id,
        'rfq_item_id' => $rfqItem->id,
        'line_no' => 2,
        'description' => 'Housing',
        'quantity' => 12,
        'uom' => 'EA',
        'unit_price' => 90,
        'delivery_date' => $periodStart->copy()->addDays(10),
        'received_qty' => 12,
        'receiving_status' => 'received',
    ]);

    $grnEarly = GoodsReceiptNote::create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'number' => 'GRN-'.Str::upper(Str::random(6)),
        'inspected_by_id' => $user->id,
        'inspected_at' => $periodStart->copy()->addDays(7),
        'status' => 'complete',
    ]);

    $grnLate = GoodsReceiptNote::create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'number' => 'GRN-'.Str::upper(Str::random(6)),
        'inspected_by_id' => $user->id,
        'inspected_at' => $periodStart->copy()->addDays(18),
        'status' => 'complete',
    ]);

    GoodsReceiptLine::create([
        'goods_receipt_note_id' => $grnEarly->id,
        'purchase_order_line_id' => $poLineEarly->id,
        'received_qty' => 10,
        'accepted_qty' => 10,
        'rejected_qty' => 0,
    ]);

    GoodsReceiptLine::create([
        'goods_receipt_note_id' => $grnLate->id,
        'purchase_order_line_id' => $poLineLate->id,
        'received_qty' => 12,
        'accepted_qty' => 9,
        'rejected_qty' => 3,
    ]);

    RfqInvitation::create([
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'invited_by' => $user->id,
        'status' => RfqInvitation::STATUS_PENDING,
    ]);

    foreach (range(1, 2) as $index) {
        $additionalRfq = RFQ::factory()->create([
            'company_id' => $company->id,
        ]);

        RfqInvitation::create([
            'rfq_id' => $additionalRfq->id,
            'supplier_id' => $supplier->id,
            'invited_by' => $user->id,
            'status' => RfqInvitation::STATUS_PENDING,
            'created_at' => $periodStart->copy()->addDays($index + 1),
            'updated_at' => $periodStart->copy()->addDays($index + 1),
        ]);
    }

    $service = app(SupplierRiskService::class);

    $result = $service->calculateForCompany($company, $periodStart);

    expect($result)->toHaveCount(1);

    $score = $result->first();

    expect((float) $score->on_time_delivery_rate)->toBe(0.5)
        ->and(round((float) $score->defect_rate, 2))->toBe(0.14)
        ->and((float) $score->lead_time_volatility)->toBeGreaterThan(0.0)
        ->and($score->risk_grade?->value)->toBe(RiskGrade::Medium->value)
        ->and($company->fresh()->risk_scores_monthly_used)->toBe(1);

    Carbon::setTestNow();
});
