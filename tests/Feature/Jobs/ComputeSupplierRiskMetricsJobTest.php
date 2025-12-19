<?php

use App\Enums\RiskGrade;
use App\Jobs\ComputeSupplierRiskMetricsJob;
use App\Models\AiEvent;
use App\Models\AiModelMetric;
use App\Models\Company;
use App\Models\GoodsReceiptLine;
use App\Models\GoodsReceiptNote;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\SupplierRiskScore;
use App\Models\User;
use App\Services\Ai\AiEventRecorder;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;

it('records bucket metrics and correlation for supplier risk outcomes', function (): void {
    $now = Carbon::parse('2025-12-18 12:00:00', 'UTC');
    Carbon::setTestNow($now);

    $company = Company::factory()->create();

    CompanyContext::forCompany($company->id, function () use ($company, $now): void {
        $highSupplier = CompanyContext::bypass(fn () => Supplier::factory()->create(['company_id' => null]));
        $lowSupplier = CompanyContext::bypass(fn () => Supplier::factory()->create(['company_id' => null]));

        SupplierRiskScore::query()->create([
            'company_id' => $company->id,
            'supplier_id' => $highSupplier->id,
            'overall_score' => 0.9,
            'risk_grade' => RiskGrade::High,
        ]);

        SupplierRiskScore::query()->create([
            'company_id' => $company->id,
            'supplier_id' => $lowSupplier->id,
            'overall_score' => 0.2,
            'risk_grade' => RiskGrade::Low,
        ]);

        createSupplierDeliverySample(
            company: $company,
            supplier: $highSupplier,
            promisedDate: $now->copy()->subDays(5),
            actualDate: $now->copy()->subDays(1),
            receivedQty: 10,
            rejectedQty: 4,
        );

        createSupplierDeliverySample(
            company: $company,
            supplier: $lowSupplier,
            promisedDate: $now->copy()->subDays(3),
            actualDate: $now->copy()->subDays(3),
            receivedQty: 8,
            rejectedQty: 0,
        );
    });

    $job = new ComputeSupplierRiskMetricsJob($company->id, 30);
    $job->handle(app(AiEventRecorder::class));

    CompanyContext::forCompany($company->id, function (): void {
        $lateHigh = AiModelMetric::query()->where('metric_name', 'risk_bucket_late_rate_high')->first();
        $defectHigh = AiModelMetric::query()->where('metric_name', 'risk_bucket_defect_rate_high')->first();
        $lateLow = AiModelMetric::query()->where('metric_name', 'risk_bucket_late_rate_low')->first();
        $correlation = AiModelMetric::query()->where('metric_name', 'risk_score_late_rate_correlation')->first();

        expect($lateHigh)->not()->toBeNull();
        expect((float) $lateHigh->metric_value)->toBe(1.0);
        expect($defectHigh)->not()->toBeNull();
        expect((float) $defectHigh->metric_value)->toBe(0.4);
        expect($lateLow)->not()->toBeNull();
        expect((float) $lateLow->metric_value)->toBe(0.0);
        expect($correlation)->not()->toBeNull();
        expect($correlation->notes['sample_size'] ?? null)->toBe(2);
    });

    expect(AiEvent::query()->where('feature', 'supplier_risk_metrics')->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('skips metrics when no supplier outcomes exist', function (): void {
    $company = Company::factory()->create();

    CompanyContext::forCompany($company->id, function () use ($company): void {
        $supplier = CompanyContext::bypass(fn () => Supplier::factory()->create(['company_id' => null]));

        SupplierRiskScore::query()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'overall_score' => 0.55,
            'risk_grade' => RiskGrade::Medium,
        ]);
    });

    $job = new ComputeSupplierRiskMetricsJob($company->id, 30);
    $job->handle(app(AiEventRecorder::class));

    CompanyContext::forCompany($company->id, function (): void {
        expect(AiModelMetric::query()->count())->toBe(0);
    });

    expect(AiEvent::query()->where('feature', 'supplier_risk_metrics')->exists())->toBeFalse();
});

function createSupplierDeliverySample(
    Company $company,
    Supplier $supplier,
    CarbonInterface $promisedDate,
    CarbonInterface $actualDate,
    int $receivedQty,
    int $rejectedQty
): void {
    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'status' => 'confirmed',
    ]);

    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'delivery_date' => $promisedDate->toDateString(),
    ]);

    $inspector = User::factory()->create(['company_id' => $company->id]);

    $grn = GoodsReceiptNote::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'inspected_by_id' => $inspector->id,
        'inspected_at' => $actualDate,
        'status' => 'complete',
    ]);

    $line = GoodsReceiptLine::query()->create([
        'goods_receipt_note_id' => $grn->id,
        'purchase_order_line_id' => $poLine->id,
        'received_qty' => $receivedQty,
        'accepted_qty' => max(0, $receivedQty - $rejectedQty),
        'rejected_qty' => max(0, $rejectedQty),
    ]);

    $line->forceFill([
        'created_at' => $actualDate,
        'updated_at' => $actualDate,
    ])->save();
}
