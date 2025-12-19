<?php

namespace App\Jobs;

use App\Models\AiEvent;
use App\Models\AiModelMetric;
use App\Models\ForecastSnapshot;
use App\Models\InventoryTxn;
use App\Services\Ai\AiEventRecorder;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ComputeForecastAccuracyMetricsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const COMPANY_CHUNK = 100;
    private const SNAPSHOT_CHUNK = 200;

    /**
     * Inventory transaction types that count as consumption in MAPE/MAE calculations.
     *
     * @var list<string>
     */
    private const CONSUMPTION_TYPES = [
        'issue',
        'adjust_out',
        'transfer_out',
        'return_out',
    ];

    public function __construct(private readonly ?int $companyId = null)
    {
    }

    public function handle(AiEventRecorder $recorder): void
    {
        $companyIds = $this->companyId !== null ? [$this->companyId] : $this->resolveCompanyIds();

        foreach ($companyIds as $companyId) {
            $this->processCompany((int) $companyId, $recorder);
        }
    }

    /**
     * @return list<int>
     */
    private function resolveCompanyIds(): array
    {
        $companyIds = [];

        CompanyContext::bypass(function () use (&$companyIds): void {
            ForecastSnapshot::query()
                ->select('company_id')
                ->whereNotNull('company_id')
                ->distinct()
                ->orderBy('company_id')
                ->chunk(self::COMPANY_CHUNK, function ($rows) use (&$companyIds): void {
                    foreach ($rows as $row) {
                        $companyIds[] = (int) $row->company_id;
                    }
                });
        });

        return array_values(array_unique($companyIds));
    }

    private function processCompany(int $companyId, AiEventRecorder $recorder): void
    {
        $summary = [
            'evaluated' => 0,
            'metrics' => 0,
            'failures' => 0,
        ];

        CompanyContext::forCompany($companyId, function () use (&$summary): void {
            ForecastSnapshot::query()
                ->whereNotNull('period_end')
                ->whereNotNull('horizon_days')
                ->where('horizon_days', '>', 0)
                ->orderBy('id')
                ->chunkById(self::SNAPSHOT_CHUNK, function ($snapshots) use (&$summary): void {
                    foreach ($snapshots as $snapshot) {
                        $result = $this->evaluateSnapshot($snapshot);

                        $summary['evaluated'] += $result['evaluated'];
                        $summary['metrics'] += $result['metrics'];
                        $summary['failures'] += $result['failures'];
                    }
                });
        });

        if ($summary['evaluated'] === 0 && $summary['metrics'] === 0 && $summary['failures'] === 0) {
            return;
        }

        $recorder->record(
            companyId: $companyId,
            userId: null,
            feature: 'forecast_metrics',
            requestPayload: [
                'snapshots_evaluated' => $summary['evaluated'],
            ],
            responsePayload: [
                'metrics_written' => $summary['metrics'],
                'failures' => $summary['failures'],
            ],
            status: $summary['failures'] > 0 ? AiEvent::STATUS_ERROR : AiEvent::STATUS_SUCCESS,
            errorMessage: $summary['failures'] > 0 ? 'Some forecast metrics could not be recorded.' : null,
        );
    }

    /**
     * @return array{evaluated:int,metrics:int,failures:int}
     */
    private function evaluateSnapshot(ForecastSnapshot $snapshot): array
    {
        $periodEnd = $snapshot->period_end instanceof CarbonInterface
            ? $snapshot->period_end->copy()
            : ($snapshot->period_end ? Carbon::parse($snapshot->period_end) : null);

        $horizon = (int) ($snapshot->horizon_days ?? 0);

        if ($periodEnd === null || $horizon <= 0 || $snapshot->part_id === null) {
            return ['evaluated' => 0, 'metrics' => 0, 'failures' => 0];
        }

        $windowStart = $periodEnd->copy()->addDay()->startOfDay();
        $windowEnd = $windowStart->copy()->addDays($horizon)->endOfDay();

        if (Carbon::now()->lt($windowEnd)) {
            return ['evaluated' => 0, 'metrics' => 0, 'failures' => 0];
        }

        $actualDemand = $this->calculateActualDemand($snapshot->part_id, $windowStart, $windowEnd);
        $forecastDemand = (float) ($snapshot->demand_qty ?? 0.0);
        $absoluteError = abs($actualDemand - $forecastDemand);
        $mape = $actualDemand !== 0.0 ? $absoluteError / max(1e-6, abs($actualDemand)) : null;

        $notes = [
            'snapshot_id' => $snapshot->id,
            'part_id' => $snapshot->part_id,
            'horizon_days' => $horizon,
            'forecast_qty' => $forecastDemand,
            'actual_qty' => $actualDemand,
            'method' => $snapshot->method,
            'window_start' => $windowStart->toIso8601String(),
            'window_end' => $windowEnd->toIso8601String(),
        ];

        $metricsWritten = 0;

        try {
            $metricsWritten += $this->storeMetric($snapshot, 'mae', $absoluteError, $windowStart, $windowEnd, $notes);

            if ($mape !== null) {
                $metricsWritten += $this->storeMetric($snapshot, 'mape', $mape, $windowStart, $windowEnd, $notes);
            }
        } catch (Throwable $exception) {
            Log::warning('Failed to persist forecast accuracy metric', [
                'snapshot_id' => $snapshot->id,
                'company_id' => $snapshot->company_id,
                'part_id' => $snapshot->part_id,
                'message' => $exception->getMessage(),
            ]);

            return ['evaluated' => 1, 'metrics' => $metricsWritten, 'failures' => 1];
        }

        return ['evaluated' => 1, 'metrics' => $metricsWritten, 'failures' => 0];
    }

    private function calculateActualDemand(int $partId, CarbonInterface $start, CarbonInterface $end): float
    {
        return (float) InventoryTxn::query()
            ->where('part_id', $partId)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('type', self::CONSUMPTION_TYPES)
            ->selectRaw('COALESCE(SUM(ABS(qty)), 0) as total_qty')
            ->value('total_qty');
    }

    private function storeMetric(
        ForecastSnapshot $snapshot,
        string $metricName,
        float $metricValue,
        CarbonInterface $windowStart,
        CarbonInterface $windowEnd,
        array $notes
    ): int {
        $attributes = [
            'company_id' => $snapshot->company_id,
            'feature' => 'forecast',
            'entity_type' => 'part',
            'entity_id' => $snapshot->part_id,
            'metric_name' => $metricName,
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
        ];

        $values = [
            'metric_value' => round($metricValue, 6),
            'notes' => array_merge($notes, ['metric' => $metricName]),
        ];

        AiModelMetric::query()->updateOrCreate($attributes, $values);

        return 1;
    }
}
