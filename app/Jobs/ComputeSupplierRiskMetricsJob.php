<?php

namespace App\Jobs;

use App\Enums\RiskGrade;
use App\Models\AiEvent;
use App\Models\AiModelMetric;
use App\Models\SupplierRiskScore;
use App\Services\Ai\AiEventRecorder;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ComputeSupplierRiskMetricsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const COMPANY_CHUNK = 100;

    public function __construct(
        private readonly ?int $companyId = null,
        private readonly int $windowDays = 30,
    ) {
    }

    public function handle(AiEventRecorder $recorder): void
    {
        $companyIds = $this->companyId !== null ? [$this->companyId] : $this->resolveCompanyIds();

        foreach ($companyIds as $companyId) {
            $summary = CompanyContext::forCompany($companyId, function () use ($companyId): array {
                return $this->captureMetricsForCompany($companyId);
            });

            if ($summary['samples'] === 0 && $summary['metrics'] === 0) {
                continue;
            }

            $recorder->record(
                companyId: $companyId,
                userId: null,
                feature: 'supplier_risk_metrics',
                requestPayload: [
                    'window_days' => $this->windowDays,
                    'samples' => $summary['samples'],
                ],
                responsePayload: [
                    'metrics_written' => $summary['metrics'],
                    'failures' => $summary['failures'],
                ],
                status: $summary['failures'] > 0 ? AiEvent::STATUS_ERROR : AiEvent::STATUS_SUCCESS,
                errorMessage: $summary['failures'] > 0 ? 'Some supplier risk metrics could not be stored.' : null,
            );
        }
    }

    /**
     * @return list<int>
     */
    private function resolveCompanyIds(): array
    {
        $companyIds = [];

        CompanyContext::bypass(function () use (&$companyIds): void {
            SupplierRiskScore::query()
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

    /**
     * @return array{samples:int,metrics:int,failures:int}
     */
    private function captureMetricsForCompany(int $companyId): array
    {
        $windowEnd = Carbon::now();
        $windowStart = $windowEnd->copy()->subDays(max(1, $this->windowDays));

        $samples = $this->collectSupplierSamples($companyId, $windowStart, $windowEnd);

        if ($samples === []) {
            return ['samples' => 0, 'metrics' => 0, 'failures' => 0];
        }

        $bucketStats = $this->buildBucketStats($samples);
        $correlation = $this->computeLateRateCorrelation($samples);

        $metricsWritten = 0;
        $failures = 0;

        foreach ($bucketStats as $bucket => $stat) {
            if ($stat['late_rate'] !== null) {
                try {
                    $this->storeMetric(
                        companyId: $companyId,
                        metricName: "risk_bucket_late_rate_{$bucket}",
                        metricValue: $stat['late_rate'],
                        windowStart: $windowStart,
                        windowEnd: $windowEnd,
                        entityType: 'supplier_bucket',
                        entityId: null,
                        notes: [
                            'bucket' => $bucket,
                            'supplier_count' => $stat['supplier_count'],
                            'sample_size' => $stat['late_samples'],
                            'metric' => 'late_rate',
                            'window_days' => $this->windowDays,
                        ],
                    );
                    $metricsWritten++;
                } catch (Throwable $exception) {
                    $failures++;
                    Log::warning('Failed to store supplier late rate metric', [
                        'company_id' => $companyId,
                        'bucket' => $bucket,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            if ($stat['defect_rate'] !== null) {
                try {
                    $this->storeMetric(
                        companyId: $companyId,
                        metricName: "risk_bucket_defect_rate_{$bucket}",
                        metricValue: $stat['defect_rate'],
                        windowStart: $windowStart,
                        windowEnd: $windowEnd,
                        entityType: 'supplier_bucket',
                        entityId: null,
                        notes: [
                            'bucket' => $bucket,
                            'supplier_count' => $stat['supplier_count'],
                            'sample_size' => $stat['defect_samples'],
                            'metric' => 'defect_rate',
                            'window_days' => $this->windowDays,
                        ],
                    );
                    $metricsWritten++;
                } catch (Throwable $exception) {
                    $failures++;
                    Log::warning('Failed to store supplier defect rate metric', [
                        'company_id' => $companyId,
                        'bucket' => $bucket,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }
        }

        if ($correlation['value'] !== null) {
            try {
                $this->storeMetric(
                    companyId: $companyId,
                    metricName: 'risk_score_late_rate_correlation',
                    metricValue: $correlation['value'],
                    windowStart: $windowStart,
                    windowEnd: $windowEnd,
                    entityType: 'supplier_analysis',
                    entityId: null,
                    notes: [
                        'sample_size' => $correlation['count'],
                        'metric' => 'pearson_risk_vs_late_rate',
                        'window_days' => $this->windowDays,
                    ],
                );
                $metricsWritten++;
            } catch (Throwable $exception) {
                $failures++;
                Log::warning('Failed to store supplier correlation metric', [
                    'company_id' => $companyId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'samples' => count($samples),
            'metrics' => $metricsWritten,
            'failures' => $failures,
        ];
    }

    /**
     * @return array<int, array{supplier_id:int,bucket:string,late_rate:float,defect_rate:float|null,risk_score:float|null,receipt_count:int}>
     */
    private function collectSupplierSamples(int $companyId, CarbonInterface $windowStart, CarbonInterface $windowEnd): array
    {
        $outcomes = $this->querySupplierOutcomes($companyId, $windowStart, $windowEnd);

        if ($outcomes->isEmpty()) {
            return [];
        }

        $supplierIds = $outcomes->pluck('supplier_id')->filter()->unique()->values();

        $riskScores = SupplierRiskScore::query()
            ->where('company_id', $companyId)
            ->whereIn('supplier_id', $supplierIds)
            ->get()
            ->keyBy('supplier_id');

        $samples = [];

        foreach ($outcomes as $outcome) {
            $supplierId = (int) ($outcome->supplier_id ?? 0);
            $score = $riskScores->get($supplierId);

            if ($supplierId === 0 || $score === null) {
                continue;
            }

            $bucket = $this->resolveRiskBucket($score->risk_grade, $score->overall_score);
            if ($bucket === null) {
                continue;
            }

            $receiptCount = (int) $outcome->receipt_count;
            if ($receiptCount <= 0) {
                continue;
            }

            $lateRate = (float) $outcome->late_receipts / max(1, $receiptCount);
            $receivedQty = (float) $outcome->received_qty;
            $defectRate = $receivedQty > 0 ? (float) $outcome->rejected_qty / max(1e-6, $receivedQty) : null;

            $samples[] = [
                'supplier_id' => $supplierId,
                'bucket' => $bucket,
                'late_rate' => $this->clampRatio($lateRate),
                'defect_rate' => $defectRate !== null ? $this->clampRatio($defectRate) : null,
                'risk_score' => $score->overall_score !== null ? (float) $score->overall_score : null,
                'receipt_count' => $receiptCount,
            ];
        }

        return $samples;
    }

    private function clampRatio(float $value): float
    {
        if ($value < 0.0) {
            return 0.0;
        }

        if ($value > 1.0) {
            return 1.0;
        }

        return $value;
    }

    private function resolveRiskBucket(?RiskGrade $grade, ?float $score): ?string
    {
        if ($grade instanceof RiskGrade) {
            return $grade->value;
        }

        if ($score === null) {
            return null;
        }

        if ($score >= 0.7) {
            return RiskGrade::High->value;
        }

        if ($score >= 0.4) {
            return RiskGrade::Medium->value;
        }

        return RiskGrade::Low->value;
    }

    /**
     * @return array<string, array{supplier_count:int,late_rate:float|null,late_samples:int,defect_rate:float|null,defect_samples:int}>
     */
    private function buildBucketStats(array $samples): array
    {
        $buckets = [
            RiskGrade::Low->value => ['late_rates' => [], 'defect_rates' => [], 'supplier_ids' => []],
            RiskGrade::Medium->value => ['late_rates' => [], 'defect_rates' => [], 'supplier_ids' => []],
            RiskGrade::High->value => ['late_rates' => [], 'defect_rates' => [], 'supplier_ids' => []],
        ];

        foreach ($samples as $sample) {
            $bucket = $sample['bucket'];
            if (! array_key_exists($bucket, $buckets)) {
                continue;
            }

            $buckets[$bucket]['supplier_ids'][$sample['supplier_id']] = true;

            if ($sample['late_rate'] !== null) {
                $buckets[$bucket]['late_rates'][] = $sample['late_rate'];
            }

            if ($sample['defect_rate'] !== null) {
                $buckets[$bucket]['defect_rates'][] = $sample['defect_rate'];
            }
        }

        $stats = [];

        foreach ($buckets as $bucket => $data) {
            $stats[$bucket] = [
                'supplier_count' => count($data['supplier_ids']),
                'late_rate' => $this->average($data['late_rates']),
                'late_samples' => count($data['late_rates']),
                'defect_rate' => $this->average($data['defect_rates']),
                'defect_samples' => count($data['defect_rates']),
            ];
        }

        return $stats;
    }

    private function average(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        return array_sum($values) / count($values);
    }

    /**
     * @return array{value:float|null,count:int}
     */
    private function computeLateRateCorrelation(array $samples): array
    {
        $pairs = array_values(array_filter($samples, static function (array $sample): bool {
            return $sample['late_rate'] !== null && $sample['risk_score'] !== null;
        }));

        $count = count($pairs);
        if ($count < 2) {
            return ['value' => null, 'count' => $count];
        }

        $scores = array_column($pairs, 'risk_score');
        $lateRates = array_column($pairs, 'late_rate');

        $value = $this->pearsonCorrelation($scores, $lateRates);

        return ['value' => $value, 'count' => $count];
    }

    private function pearsonCorrelation(array $xs, array $ys): float
    {
        $count = count($xs);
        if ($count === 0) {
            return 0.0;
        }

        $meanX = array_sum($xs) / $count;
        $meanY = array_sum($ys) / $count;

        $numerator = 0.0;
        $sumSqX = 0.0;
        $sumSqY = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $dx = $xs[$i] - $meanX;
            $dy = $ys[$i] - $meanY;
            $numerator += $dx * $dy;
            $sumSqX += $dx ** 2;
            $sumSqY += $dy ** 2;
        }

        $denominator = sqrt($sumSqX) * sqrt($sumSqY);

        if ($denominator == 0.0) {
            return 0.0;
        }

        return max(-1.0, min(1.0, $numerator / $denominator));
    }

    private function storeMetric(
        int $companyId,
        string $metricName,
        float $metricValue,
        CarbonInterface $windowStart,
        CarbonInterface $windowEnd,
        string $entityType,
        ?int $entityId,
        array $notes
    ): void {
        $attributes = [
            'company_id' => $companyId,
            'feature' => 'supplier_risk',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metric_name' => $metricName,
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
        ];

        $values = [
            'metric_value' => round($metricValue, 6),
            'notes' => array_merge($notes, [
                'window_start' => $windowStart->toIso8601String(),
                'window_end' => $windowEnd->toIso8601String(),
            ]),
        ];

        AiModelMetric::query()->updateOrCreate($attributes, $values);
    }

    private function querySupplierOutcomes(
        int $companyId,
        CarbonInterface $windowStart,
        CarbonInterface $windowEnd
    ): Collection {
        $actualDateSql = 'COALESCE(grn.inspected_at, grn.created_at)';
        $actualDate = DB::raw($actualDateSql);

        /** @var Builder $builder */
        $builder = DB::table('purchase_orders as po')
            ->selectRaw('po.supplier_id as supplier_id')
            ->selectRaw('COUNT(grl.id) as receipt_count')
            ->selectRaw('SUM(CASE WHEN pol.delivery_date IS NOT NULL AND DATE(' . $actualDateSql . ') > pol.delivery_date THEN 1 ELSE 0 END) as late_receipts')
            ->selectRaw('SUM(COALESCE(grl.received_qty, 0)) as received_qty')
            ->selectRaw('SUM(COALESCE(grl.rejected_qty, 0)) as rejected_qty')
            ->join('goods_receipt_notes as grn', 'grn.purchase_order_id', '=', 'po.id')
            ->join('goods_receipt_lines as grl', 'grl.goods_receipt_note_id', '=', 'grn.id')
            ->join('po_lines as pol', 'pol.id', '=', 'grl.purchase_order_line_id')
            ->where('po.company_id', $companyId)
            ->whereNull('po.deleted_at')
            ->whereNull('grn.deleted_at')
            ->whereNull('grl.deleted_at')
            ->whereBetween(DB::raw($actualDateSql), [$windowStart, $windowEnd])
            ->groupBy('po.supplier_id');

        return $builder->get();
    }
}
