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
use App\Models\SupplierRiskScore;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('generates risk scores and updates supplier grade', function (): void {
    $periodStart = Carbon::create(2025, 3, 1, 0, 0, 0, 'UTC');
    Carbon::setTestNow($periodStart->copy()->addDays(10));

    $plan = Plan::factory()->create([
        'code' => 'growth-risk',
        'rfqs_per_month' => 100,
        'invoices_per_month' => 100,
        'users_max' => 25,
        'storage_gb' => 50,
        'analytics_enabled' => true,
        'analytics_history_months' => 24,
        'risk_scores_enabled' => true,
        'risk_history_months' => 12,
    ]);

    $company = Company::factory()->create([
        'plan_code' => $plan->code,
        'status' => 'active',
        'rfqs_monthly_used' => 0,
        'invoices_monthly_used' => 0,
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
        'role' => 'buyer_admin',
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
        'submitted_by' => null,
        'currency' => 'USD',
        'unit_price' => 120,
        'min_order_qty' => 10,
        'lead_time_days' => 14,
        'status' => 'awarded',
        'revision_no' => 1,
    ]);

    $quotationTwo = Quote::create([
        'company_id' => $company->id,
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'submitted_by' => null,
        'currency' => 'USD',
        'unit_price' => 80,
        'min_order_qty' => 5,
        'lead_time_days' => 10,
        'status' => 'awarded',
        'revision_no' => 2,
    ]);

    $purchaseOrder = PurchaseOrder::create([
        'company_id' => $company->id,
        'rfq_id' => $rfq->id,
        'quote_id' => $quote->id,
        'po_number' => 'PO-'.Str::upper(Str::random(6)),
        'currency' => 'USD',
        'status' => 'sent',
    ]);

    $poLineOnTime = PurchaseOrderLine::create([
        'purchase_order_id' => $purchaseOrder->id,
        'rfq_item_id' => $rfqItem->id,
        'line_no' => 1,
        'description' => 'Bracket',
        'quantity' => 10,
        'uom' => 'EA',
        'unit_price' => 120,
        'delivery_date' => $periodStart->copy()->addDays(5),
        'received_qty' => 10,
        'receiving_status' => 'received',
    ]);

    $poLineLate = PurchaseOrderLine::create([
        'purchase_order_id' => $purchaseOrder->id,
        'rfq_item_id' => $rfqItem->id,
        'line_no' => 2,
        'description' => 'Plate',
        'quantity' => 10,
        'uom' => 'EA',
        'unit_price' => 80,
        'delivery_date' => $periodStart->copy()->addDays(10),
        'received_qty' => 10,
        'receiving_status' => 'received',
    ]);

    $grnOnTime = GoodsReceiptNote::create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'number' => 'GRN-'.Str::upper(Str::random(6)),
        'inspected_by_id' => $user->id,
        'inspected_at' => $periodStart->copy()->addDays(5),
        'status' => 'complete',
    ]);

    $grnLate = GoodsReceiptNote::create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'number' => 'GRN-'.Str::upper(Str::random(6)),
        'inspected_by_id' => $user->id,
        'inspected_at' => $periodStart->copy()->addDays(20),
        'status' => 'complete',
    ]);

    GoodsReceiptLine::create([
        'goods_receipt_note_id' => $grnOnTime->id,
        'purchase_order_line_id' => $poLineOnTime->id,
        'received_qty' => 10,
        'accepted_qty' => 10,
        'rejected_qty' => 0,
    ]);

    GoodsReceiptLine::create([
        'goods_receipt_note_id' => $grnLate->id,
        'purchase_order_line_id' => $poLineLate->id,
        'received_qty' => 10,
        'accepted_qty' => 7,
        'rejected_qty' => 3,
    ]);

    RfqInvitation::create([
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'invited_by' => $user->id,
        'status' => 'invited',
        'created_at' => $periodStart->copy()->addDays(1),
        'updated_at' => $periodStart->copy()->addDays(1),
    ]);

    foreach (range(2, 4) as $index) {
        $additionalRfq = RFQ::factory()->create([
            'company_id' => $company->id,
        ]);

        RfqInvitation::create([
            'rfq_id' => $additionalRfq->id,
            'supplier_id' => $supplier->id,
            'invited_by' => $user->id,
            'status' => 'invited',
            'created_at' => $periodStart->copy()->addDays($index),
            'updated_at' => $periodStart->copy()->addDays($index),
        ]);
    }

    $quote->updateQuietly([
        'created_at' => $periodStart->copy()->addDays(2),
        'updated_at' => $periodStart->copy()->addDays(2),
    ]);

    $quotationTwo->updateQuietly([
        'created_at' => $periodStart->copy()->addDays(3),
        'updated_at' => $periodStart->copy()->addDays(3),
    ]);

    actingAs($user);

    $response = $this->postJson('/api/risk/generate');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Supplier risk scores generated.')
        ->assertJsonPath('meta.generated', 1);

    $score = SupplierRiskScore::first();

    expect($score)->not->toBeNull()
        ->and($score->risk_grade?->value)->toBe(RiskGrade::Medium->value)
        ->and((float) $score->overall_score)->toBeGreaterThan(0.0)
        ->and($score->badges_json)->toContain('Elevated defect rate')
        ->and($score->badges_json)->toContain('Lead time variance high');

    expect($supplier->fresh()->risk_grade?->value)->toBe(RiskGrade::Medium->value);
    expect($company->fresh()->risk_scores_monthly_used)->toBe(1);

    Carbon::setTestNow();
});

it('enforces risk history limits when usage exceeded', function (): void {
    $plan = Plan::factory()->create([
        'code' => 'limited-risk',
        'risk_scores_enabled' => true,
        'risk_history_months' => 1,
    ]);

    $company = Company::factory()->create([
        'plan_code' => $plan->code,
        'risk_scores_monthly_used' => 1,
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

    actingAs($user);

    $response = $this->postJson('/api/risk/generate');

    $response->assertStatus(402)
        ->assertJsonPath('status', 'error');
});

it('filters risk scores by grade', function (): void {
    $plan = Plan::factory()->create([
        'risk_scores_enabled' => true,
    ]);

    $company = Company::factory()->create([
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

    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
    ]);

    SupplierRiskScore::create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'on_time_delivery_rate' => 0.9,
        'defect_rate' => 0.05,
        'price_volatility' => 0.1,
        'lead_time_volatility' => 0.1,
        'responsiveness_rate' => 0.9,
        'overall_score' => 0.2,
        'risk_grade' => RiskGrade::Low->value,
        'badges_json' => ['Performance stable'],
        'meta' => ['period_key' => '2025-03'],
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    actingAs($user);

    $response = $this->getJson('/api/risk?grade=low');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.risk_grade', RiskGrade::Low->value);
});
