<?php

use App\Enums\ReorderMethod;
use App\Jobs\ComputeForecastAccuracyMetricsJob;
use App\Models\AiEvent;
use App\Models\AiModelMetric;
use App\Models\Company;
use App\Models\ForecastSnapshot;
use App\Models\InventoryTxn;
use App\Models\Part;
use App\Models\Warehouse;
use App\Services\Ai\AiEventRecorder;
use App\Support\CompanyContext;
use Carbon\Carbon;

it('computes MAE and MAPE metrics for completed forecast windows', function (): void {
    $company = Company::factory()->create();
    $part = Part::factory()->for($company)->create();
    $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
    $periodEnd = Carbon::now()->subDays(10)->startOfDay();
    $horizon = 5;

    CompanyContext::forCompany($company->id, function () use ($company, $part, $warehouse, $periodEnd, $horizon): void {
        ForecastSnapshot::query()->create([
            'company_id' => $company->id,
            'part_id' => $part->id,
            'period_start' => $periodEnd->copy()->subDays(14),
            'period_end' => $periodEnd,
            'demand_qty' => 10,
            'avg_daily_demand' => 2,
            'method' => ReorderMethod::Ema->value,
            'horizon_days' => $horizon,
        ]);

        $windowStart = $periodEnd->copy()->addDay()->startOfDay();

        for ($day = 0; $day < $horizon; $day++) {
            $timestamp = $windowStart->copy()->addDays($day)->addHours(1);

            $txn = InventoryTxn::query()->create([
                'company_id' => $company->id,
                'part_id' => $part->id,
                'warehouse_id' => $warehouse->id,
                'bin_id' => null,
                'type' => 'issue',
                'qty' => 3,
                'uom' => 'ea',
                'ref_type' => null,
                'ref_id' => null,
                'note' => null,
                'performed_by' => null,
            ]);

            $txn->forceFill([
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->save();
        }
    });

    $job = new ComputeForecastAccuracyMetricsJob($company->id);
    $job->handle(app(AiEventRecorder::class));

    CompanyContext::forCompany($company->id, function () use ($company): void {
        expect(AiModelMetric::query()->count())->toBe(2);

        $mae = AiModelMetric::query()->where('metric_name', 'mae')->first();
        $mape = AiModelMetric::query()->where('metric_name', 'mape')->first();

        expect($mae)->not()->toBeNull();
        expect($mape)->not()->toBeNull();

        expect((float) $mae->metric_value)->toBe(5.0);
        expect(round((float) $mape->metric_value, 5))->toBe(0.33333);
    });

    CompanyContext::forCompany($company->id, function (): void {
        expect(AiEvent::query()->where('feature', 'forecast_metrics')->exists())->toBeTrue();
    });
});

it('skips forecast snapshots whose actual window has not completed', function (): void {
    $company = Company::factory()->create();
    $part = Part::factory()->for($company)->create();

    CompanyContext::forCompany($company->id, function () use ($company, $part): void {
        ForecastSnapshot::query()->create([
            'company_id' => $company->id,
            'part_id' => $part->id,
            'period_start' => Carbon::now()->subDays(3),
            'period_end' => Carbon::now()->subDays(1),
            'demand_qty' => 5,
            'avg_daily_demand' => 1,
            'method' => ReorderMethod::Ema->value,
            'horizon_days' => 14,
        ]);
    });

    $job = new ComputeForecastAccuracyMetricsJob($company->id);
    $job->handle(app(AiEventRecorder::class));

    CompanyContext::forCompany($company->id, function (): void {
        expect(AiModelMetric::query()->count())->toBe(0);
        expect(AiEvent::query()->where('feature', 'forecast_metrics')->exists())->toBeFalse();
    });
});
