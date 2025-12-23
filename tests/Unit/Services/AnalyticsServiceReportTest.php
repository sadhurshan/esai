<?php

use App\Enums\InventoryTxnType;
use App\Enums\RiskGrade;
use App\Models\Company;
use App\Models\ForecastSnapshot;
use App\Models\GoodsReceiptLine;
use App\Models\GoodsReceiptNote;
use App\Models\InventorySetting;
use App\Models\InventoryTxn;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\SupplierRiskScore;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds forecast report aggregates from inventory usage and snapshots', function (): void {
    $service = app(AnalyticsService::class);

    $company = Company::factory()->create();

    $part = Part::factory()->create([
        'company_id' => $company->id,
        'name' => 'Widget A',
        'category' => 'critical',
    ]);

    $warehouse = Warehouse::factory()->create([
        'company_id' => $company->id,
    ]);

    InventorySetting::factory()->create([
        'company_id' => $company->id,
        'part_id' => $part->id,
        'lead_time_days' => 5,
        'safety_stock' => 10,
        'min_qty' => 5,
        'reorder_qty' => 45,
    ]);

    InventoryTxn::factory()->create([
        'company_id' => $company->id,
        'part_id' => $part->id,
        'warehouse_id' => $warehouse->id,
        'type' => InventoryTxnType::Issue,
        'qty' => 10,
        'created_at' => Carbon::parse('2025-01-01 08:00:00'),
        'updated_at' => Carbon::parse('2025-01-01 08:00:00'),
    ]);

    InventoryTxn::factory()->create([
        'company_id' => $company->id,
        'part_id' => $part->id,
        'warehouse_id' => $warehouse->id,
        'type' => InventoryTxnType::Issue,
        'qty' => 6,
        'created_at' => Carbon::parse('2025-01-02 09:00:00'),
        'updated_at' => Carbon::parse('2025-01-02 09:00:00'),
    ]);

    ForecastSnapshot::factory()->create([
        'company_id' => $company->id,
        'part_id' => $part->id,
        'period_start' => Carbon::parse('2025-01-01'),
        'period_end' => Carbon::parse('2025-01-01'),
        'avg_daily_demand' => 8,
        'demand_qty' => 16,
        'safety_stock_qty' => 4,
        'horizon_days' => 2,
        'on_hand_qty' => 100,
        'on_order_qty' => 40,
    ]);

    $report = $service->generateForecastReport($company->id, [
        'start_date' => '2025-01-01',
        'end_date' => '2025-01-02',
        'part_ids' => [$part->id],
        'category_ids' => ['critical'],
        'location_ids' => [$warehouse->id],
    ]);

    expect($report['filters_used'])->toMatchArray([
        'start_date' => '2025-01-01',
        'end_date' => '2025-01-02',
        'part_ids' => [$part->id],
        'category_ids' => ['critical'],
        'location_ids' => [$warehouse->id],
        'bucket' => 'daily',
    ]);

    expect($report['series'])->toHaveCount(1);
    $seriesRow = $report['series'][0];

    expect($seriesRow)->toMatchArray([
        'part_id' => $part->id,
        'part_name' => $part->name,
    ]);

    expect($seriesRow['data'])->toBe([
        ['date' => '2025-01-01', 'actual' => 10.0, 'forecast' => 8.0],
        ['date' => '2025-01-02', 'actual' => 6.0, 'forecast' => 8.0],
    ]);

    expect($report['table'])->toHaveCount(1);
    $tableRow = $report['table'][0];

    expect($tableRow)->toMatchArray([
        'part_id' => $part->id,
        'part_name' => $part->name,
        'total_forecast' => 16.0,
        'total_actual' => 16.0,
        'mape' => 0.25,
        'mae' => 2.0,
        'reorder_point' => 50.0,
        'safety_stock' => 10.0,
    ]);

    expect($report['aggregates'])->toMatchArray([
        'total_forecast' => 16.0,
        'total_actual' => 16.0,
        'mape' => 0.25,
        'mae' => 2.0,
        'avg_daily_demand' => 8.0,
        'recommended_reorder_point' => 50.0,
        'recommended_safety_stock' => 10.0,
    ]);
});

it('builds supplier performance report with bucketed metrics and risk data', function (): void {
    $service = app(AnalyticsService::class);

    $company = Company::factory()->create();
    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
        'name' => 'Acme Components',
        'status' => 'approved',
    ]);

    $inspector = User::factory()->create([
        'company_id' => $company->id,
    ]);

    $poOne = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'status' => 'sent',
    ]);
    $poOne->forceFill([
        'ordered_at' => Carbon::parse('2025-01-01 09:00:00'),
        'sent_at' => Carbon::parse('2025-01-01 10:00:00'),
        'acknowledged_at' => Carbon::parse('2025-01-02 09:00:00'),
        'expected_at' => Carbon::parse('2025-01-05 00:00:00'),
    ])->save();

    $poTwo = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'status' => 'sent',
    ]);
    $poTwo->forceFill([
        'ordered_at' => Carbon::parse('2025-01-08 09:00:00'),
        'sent_at' => Carbon::parse('2025-01-08 10:00:00'),
        'acknowledged_at' => Carbon::parse('2025-01-11 09:00:00'),
        'expected_at' => Carbon::parse('2025-01-13 00:00:00'),
    ])->save();

    $poOneLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $poOne->id,
        'quantity' => 100,
        'unit_price' => 100,
        'delivery_date' => '2025-01-05',
    ]);

    $poTwoLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $poTwo->id,
        'quantity' => 50,
        'unit_price' => 200,
        'delivery_date' => '2025-01-13',
    ]);

    $grnOne = GoodsReceiptNote::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $poOne->id,
        'inspected_by_id' => $inspector->id,
        'inspected_at' => Carbon::parse('2025-01-04 12:00:00'),
        'status' => 'complete',
    ]);

    $grnTwo = GoodsReceiptNote::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $poTwo->id,
        'inspected_by_id' => $inspector->id,
        'inspected_at' => Carbon::parse('2025-01-13 09:00:00'),
        'status' => 'complete',
    ]);

    GoodsReceiptLine::query()->create([
        'goods_receipt_note_id' => $grnOne->id,
        'purchase_order_line_id' => $poOneLine->id,
        'received_qty' => 100,
        'accepted_qty' => 95,
        'rejected_qty' => 5,
    ]);

    GoodsReceiptLine::query()->create([
        'goods_receipt_note_id' => $grnTwo->id,
        'purchase_order_line_id' => $poTwoLine->id,
        'received_qty' => 50,
        'accepted_qty' => 40,
        'rejected_qty' => 10,
    ]);

    SupplierRiskScore::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'overall_score' => 0.32,
        'risk_grade' => RiskGrade::Medium->value,
        'created_at' => Carbon::parse('2025-01-15 00:00:00'),
    ]);

    $report = $service->generateSupplierPerformanceReport($company->id, $supplier->id, [
        'start_date' => '2025-01-01',
        'end_date' => '2025-01-15',
    ]);

    expect($report['filters_used'])->toMatchArray([
        'start_date' => '2025-01-01',
        'end_date' => '2025-01-15',
        'supplier_id' => $supplier->id,
        'bucket' => 'weekly',
    ]);

    $series = collect($report['series'])->keyBy('metric_name');

    expect($series['on_time_delivery_rate']['data'])->toBe([
        ['date' => '2025-01-01 - 2025-01-07', 'value' => 1.0],
        ['date' => '2025-01-08 - 2025-01-14', 'value' => 0.0],
        ['date' => '2025-01-15 - 2025-01-15', 'value' => 0.0],
    ]);

    expect($series['defect_rate']['data'])->toBe([
        ['date' => '2025-01-01 - 2025-01-07', 'value' => 0.05],
        ['date' => '2025-01-08 - 2025-01-14', 'value' => 0.2],
        ['date' => '2025-01-15 - 2025-01-15', 'value' => 0.0],
    ]);

    expect($series['service_responsiveness']['data'])->toBe([
        ['date' => '2025-01-01 - 2025-01-07', 'value' => 24.0],
        ['date' => '2025-01-08 - 2025-01-14', 'value' => 72.0],
        ['date' => '2025-01-15 - 2025-01-15', 'value' => 0.0],
    ]);

    expect($series['lead_time_variance']['data'])->toBe([
        ['date' => '2025-01-01 - 2025-01-07', 'value' => 0.0],
        ['date' => '2025-01-08 - 2025-01-14', 'value' => 0.0],
        ['date' => '2025-01-15 - 2025-01-15', 'value' => 0.0],
    ]);

    expect($series['price_volatility']['data'])->toBe([
        ['date' => '2025-01-01 - 2025-01-07', 'value' => 0.0],
        ['date' => '2025-01-08 - 2025-01-14', 'value' => 0.0],
        ['date' => '2025-01-15 - 2025-01-15', 'value' => 0.0],
    ]);

    expect($report['table'])->toHaveCount(1);
    $tableRow = $report['table'][0];

    expect($tableRow)->toMatchArray([
        'supplier_id' => $supplier->id,
        'supplier_name' => 'Acme Components',
        'on_time_delivery_rate' => 0.5,
        'defect_rate' => 0.1,
        'lead_time_variance' => 0.9375,
        'price_volatility' => 50.0,
        'service_responsiveness' => 48.0,
        'risk_score' => '0.3200',
        'risk_category' => RiskGrade::Medium->value,
    ]);
});
