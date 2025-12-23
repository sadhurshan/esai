<?php

namespace App\Services;

use App\Enums\InventoryTxnType;
use App\Enums\InvoiceStatus;
use App\Models\AnalyticsSnapshot;
use App\Models\Company;
use App\Models\ForecastSnapshot;
use App\Models\GoodsReceiptLine;
use App\Models\InventoryTxn;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Models\SupplierRiskScore;
use App\Support\Audit\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /** @var list<string> */
    private const DEMAND_SIGNAL_TYPES = [
        InventoryTxnType::Issue->value,
        InventoryTxnType::AdjustOut->value,
        InventoryTxnType::TransferOut->value,
        InventoryTxnType::ReturnOut->value,
    ];

    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @return Collection<string, AnalyticsSnapshot>
     */
    public function generateForPeriod(Company $company, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        $results = collect();

        $alreadyGenerated = AnalyticsSnapshot::query()
            ->where('company_id', $company->id)
            ->where('period_start', $periodStart->toDateString())
            ->where('period_end', $periodEnd->toDateString())
            ->exists();

        foreach (AnalyticsSnapshot::TYPES as $type) {
            $computation = $this->computeMetric($company, $type, $periodStart, $periodEnd);

            $snapshot = AnalyticsSnapshot::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'type' => $type,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                ],
                [
                    'value' => $computation['value'],
                    'meta' => $computation['meta'],
                ]
            );

            if ($snapshot->wasRecentlyCreated) {
                $this->auditLogger->created($snapshot, [
                    'type' => $snapshot->type,
                    'value' => $snapshot->value,
                    'meta' => $snapshot->meta,
                ]);
            } else {
                $this->auditLogger->updated($snapshot, ['value' => $snapshot->getOriginal('value'), 'meta' => $snapshot->getOriginal('meta')], $snapshot->getChanges());
            }

            $results->put($type, $snapshot->fresh());
        }

        if (! $alreadyGenerated) {
            $company->increment('analytics_usage_months');
        }

        $company->analytics_last_generated_at = now();
        $company->save();

        return $results;
    }

    public function generateForecastReport(int $companyId, array $params): array
    {
        $filters = $this->normaliseForecastReportFilters($params);
        $bucket = $this->resolveForecastBucket($filters['start'], $filters['end']);

        $filtersUsed = [
            'start_date' => $filters['start']->toDateString(),
            'end_date' => $filters['end']->toDateString(),
            'part_ids' => $filters['part_ids'],
            // TODO: clarify with spec if category_ids should reference taxonomy IDs instead of part.category strings.
            'category_ids' => $filters['category_filters'],
            'location_ids' => $filters['location_ids'],
            'bucket' => $bucket,
        ];

        $actualUsageRows = $this->fetchActualUsageRows(
            $companyId,
            $filters['start'],
            $filters['end'],
            $filters['part_ids'],
            $filters['location_ids']
        );

        $forecastRows = $this->fetchForecastSnapshotRows(
            $companyId,
            $filters['start'],
            $filters['end'],
            $filters['part_ids']
        );

        $indexedActual = $this->indexActualUsageRows($actualUsageRows);
        [$indexedForecast, $latestSnapshots] = $this->indexForecastSnapshotRows($forecastRows);

        $candidatePartIds = $this->resolveCandidatePartIds($filters['part_ids'], $indexedActual, $indexedForecast);
        $parts = $this->loadPartMetadataForForecast($companyId, $candidatePartIds, $filters['category_filters']);

        if ($parts->isEmpty()) {
            return [
                'series' => [],
                'table' => [],
                'aggregates' => $this->emptyForecastAggregates(),
                'filters_used' => $filtersUsed,
            ];
        }

        $buckets = $this->buildTimeBuckets($filters['start'], $filters['end'], $bucket);

        $series = [];
        $table = [];
        $aggregateTotals = [
            'total_forecast' => 0.0,
            'total_actual' => 0.0,
            'abs_error_sum' => 0.0,
            'abs_error_count' => 0,
            'pct_error_sum' => 0.0,
            'pct_error_count' => 0,
            'day_count' => 0,
            'reorder_points' => [],
            'safety_stocks' => [],
        ];

        foreach ($parts as $part) {
            $partId = (int) $part->id;

            [$seriesData, $metrics] = $this->buildPartForecastSeries(
                $buckets,
                $indexedActual[$partId] ?? [],
                $indexedForecast[$partId] ?? []
            );

            $reorder = $this->calculateReorderRecommendation(
                $part,
                $latestSnapshots[$partId] ?? null,
                $metrics['avg_daily_demand']
            );

            $table[] = [
                'part_id' => $partId,
                'part_name' => $part->name ?? $part->part_number,
                'total_forecast' => round($metrics['total_forecast'], 3),
                'total_actual' => round($metrics['total_actual'], 3),
                'mape' => $metrics['mape'],
                'mae' => $metrics['mae'],
                'reorder_point' => $reorder['reorder_point'],
                'safety_stock' => $reorder['safety_stock'],
            ];

            $series[] = [
                'part_id' => $partId,
                'part_name' => $part->name ?? $part->part_number,
                'data' => $seriesData,
            ];

            $aggregateTotals['total_forecast'] += $metrics['total_forecast'];
            $aggregateTotals['total_actual'] += $metrics['total_actual'];
            $aggregateTotals['abs_error_sum'] += $metrics['abs_error_sum'];
            $aggregateTotals['abs_error_count'] += $metrics['abs_error_count'];
            $aggregateTotals['pct_error_sum'] += $metrics['pct_error_sum'];
            $aggregateTotals['pct_error_count'] += $metrics['pct_error_count'];
            $aggregateTotals['day_count'] += $metrics['day_count'];
            $aggregateTotals['reorder_points'][] = $reorder['reorder_point'];
            $aggregateTotals['safety_stocks'][] = $reorder['safety_stock'];
        }

        $aggregates = $this->buildForecastAggregates($aggregateTotals);

        return [
            'series' => $series,
            'table' => $table,
            'aggregates' => $aggregates,
            'filters_used' => $filtersUsed,
        ];
    }

    public function generateSupplierPerformanceReport(int $companyId, int $supplierId, array $params): array
    {
        $filters = $this->normaliseSupplierPerformanceFilters($params);
        $bucket = $this->resolveSupplierBucket($filters['start'], $filters['end']);
        $buckets = $this->buildSupplierTimeBuckets($filters['start'], $filters['end'], $bucket);

        $filtersUsed = [
            'start_date' => $filters['start']->toDateString(),
            'end_date' => $filters['end']->toDateString(),
            'bucket' => $bucket,
            'supplier_id' => $supplierId,
        ];

        $supplier = Supplier::query()
            ->where('company_id', $companyId)
            ->find($supplierId);

        if ($supplier === null) {
            return $this->emptySupplierPerformancePayload($supplierId, $filtersUsed);
        }

        if ($buckets === []) {
            return $this->emptySupplierPerformancePayload($supplierId, $filtersUsed, $supplier->name);
        }

        $receiptRows = $this->fetchSupplierReceiptRows($companyId, $supplierId, $filters['start'], $filters['end']);
        $orderRows = $this->fetchSupplierOrderRows($companyId, $supplierId, $filters['start'], $filters['end']);

        $bucketStats = $this->aggregateSupplierBuckets($buckets, $receiptRows, $orderRows);
        $series = $this->buildSupplierSeries($buckets, $bucketStats);
        $aggregates = $this->buildSupplierAggregates($bucketStats);

        $riskScore = SupplierRiskScore::query()
            ->where('company_id', $companyId)
            ->where('supplier_id', $supplierId)
            ->latest('created_at')
            ->first();

        $tableRow = array_merge($aggregates, [
            'supplier_id' => $supplierId,
            'supplier_name' => $supplier->name,
            'risk_score' => $riskScore?->overall_score ?? null,
            'risk_category' => $riskScore?->risk_grade?->value ?? $supplier->risk_grade?->value,
        ]);

        return [
            'series' => $series,
            'table' => [$tableRow],
            'filters_used' => $filtersUsed,
        ];
    }

    /**
     * @return array{value: float, meta: array<string, mixed>}
     */
    private function computeMetric(Company $company, string $type, Carbon $periodStart, Carbon $periodEnd): array
    {
        return match ($type) {
            AnalyticsSnapshot::TYPE_CYCLE_TIME => $this->computeCycleTime($company, $periodStart, $periodEnd),
            AnalyticsSnapshot::TYPE_OTIF => $this->computeOTIF($company, $periodStart, $periodEnd),
            AnalyticsSnapshot::TYPE_RESPONSE_RATE => $this->computeResponseRate($company, $periodStart, $periodEnd),
            AnalyticsSnapshot::TYPE_SPEND => $this->computeSpend($company, $periodStart, $periodEnd),
            AnalyticsSnapshot::TYPE_FORECAST_ACCURACY => $this->computeForecastAccuracy($company, $periodStart, $periodEnd),
            default => ['value' => 0.0, 'meta' => []],
        };
    }

    private function computeCycleTime(Company $company, Carbon $periodStart, Carbon $periodEnd): array
    {
        $rfqs = RFQ::query()
            ->where('company_id', $company->id)
            ->whereNotNull('publish_at')
            ->whereBetween('publish_at', [$periodStart, $periodEnd])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->with(['purchaseOrders' => fn ($query) => $query->orderBy('created_at')])
            ->get();

        $durations = [];

        foreach ($rfqs as $rfq) {
            $publishAt = $rfq->publish_at;
            $po = $rfq->purchaseOrders->first();

            if ($publishAt === null || $po === null || $po->created_at === null) {
                continue;
            }

            $durations[] = max(0, $publishAt->diffInDays($po->created_at));
        }

        if ($durations === []) {
            return ['value' => 0.0, 'meta' => ['sample_count' => 0]];
        }

        $average = array_sum($durations) / count($durations);

        return ['value' => round($average, 4), 'meta' => ['sample_count' => count($durations)]];
    }

    private function computeOTIF(Company $company, Carbon $periodStart, Carbon $periodEnd): array
    {
        $lines = PurchaseOrderLine::query()
            ->whereHas('purchaseOrder', function ($query) use ($company, $periodStart, $periodEnd): void {
                $query->where('company_id', $company->id)
                    ->whereBetween('created_at', [$periodStart, $periodEnd]);
            })
            ->with(['purchaseOrder'])
            ->get();

        if ($lines->isEmpty()) {
            return ['value' => 0.0, 'meta' => ['total_lines' => 0, 'on_time_lines' => 0]];
        }

        $lineIds = $lines->pluck('id');

        $grnLines = GoodsReceiptLine::query()
            ->whereIn('purchase_order_line_id', $lineIds)
            ->with('goodsReceiptNote')
            ->get()
            ->groupBy('purchase_order_line_id');

        $onTime = 0;
        $total = 0;

        foreach ($lines as $line) {
            $total++;
            $deliveryDate = $line->delivery_date ? Carbon::parse($line->delivery_date)->endOfDay() : null;
            $group = $grnLines->get($line->id, collect());

            if ($deliveryDate === null || $group->isEmpty()) {
                continue;
            }

            $receivedQty = $group->sum('received_qty');
            $latestReceipt = $group
                ->map(fn (GoodsReceiptLine $grn) => $grn->goodsReceiptNote?->inspected_at)
                ->filter()
                ->max();

            if ($latestReceipt === null) {
                continue;
            }

            if ($receivedQty >= (int) $line->quantity && Carbon::parse($latestReceipt)->lte($deliveryDate)) {
                $onTime++;
            }
        }

        $value = $total > 0 ? round($onTime / $total, 4) : 0.0;

        return ['value' => $value, 'meta' => ['total_lines' => $total, 'on_time_lines' => $onTime]];
    }

    private function computeResponseRate(Company $company, Carbon $periodStart, Carbon $periodEnd): array
    {
        $rfqs = RFQ::query()
            ->where('company_id', $company->id)
            ->whereBetween('publish_at', [$periodStart, $periodEnd])
            ->with(['quotes', 'invitations'])
            ->get();

        if ($rfqs->isEmpty()) {
            return ['value' => 0.0, 'meta' => ['rfq_count' => 0, 'quotes_submitted' => 0]];
        }

        $rates = [];
        $quotesSubmitted = 0;

        foreach ($rfqs as $rfq) {
            $responses = $rfq->quotes->count();
            $quotesSubmitted += $responses;
            $total = $rfq->open_bidding || $rfq->is_open_bidding ? max(1, $responses) : $rfq->invitations->count();

            if ($total === 0) {
                $rates[] = 0.0;
                continue;
            }

            $rates[] = min(1, $responses / $total);
        }

        $average = array_sum($rates) / count($rates);

        return [
            'value' => round($average, 4),
            'meta' => [
                'rfq_count' => count($rates),
                'quotes_submitted' => $quotesSubmitted,
            ],
        ];
    }

    private function computeSpend(Company $company, Carbon $periodStart, Carbon $periodEnd): array
    {
        $invoices = Invoice::query()
            ->where('company_id', $company->id)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->whereIn('status', [
                InvoiceStatus::Paid->value,
                InvoiceStatus::Approved->value,
                InvoiceStatus::BuyerReview->value,
                InvoiceStatus::Submitted->value,
            ])
            ->with('supplier')
            ->get();

        $total = $invoices->sum(fn (Invoice $invoice) => (float) $invoice->total);
        $invoiceCount = $invoices->count();

        $topSuppliers = $invoices
            ->groupBy(fn (Invoice $invoice) => $invoice->supplier_id ?? 0)
            ->map(function ($group, $supplierId) {
                /** @var \Illuminate\Database\Eloquent\Collection<int, Invoice> $group */
                $first = $group->first();
                $name = $first?->supplier?->name;

                return [
                    'supplier_id' => $supplierId === 0 ? null : (int) $supplierId,
                    'supplier_name' => $name ?? 'Unassigned supplier',
                    'total' => round((float) $group->sum(fn (Invoice $invoice) => (float) $invoice->total), 4),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->take(5)
            ->all();

        return [
            'value' => round((float) $total, 4),
            'meta' => [
                'invoice_count' => $invoiceCount,
                'top_suppliers' => $topSuppliers,
            ],
        ];
    }

    private function computeForecastAccuracy(Company $company, Carbon $periodStart, Carbon $periodEnd): array
    {
        $purchaseOrders = PurchaseOrder::query()
            ->where('company_id', $company->id)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->with(['quote.items.rfqItem'])
            ->get();

        $errors = [];

        foreach ($purchaseOrders as $po) {
            $quote = $po->quote;

            if (! $quote) {
                continue;
            }

            foreach ($quote->items as $item) {
                $target = (float) ($item->rfqItem?->target_price ?? 0);
                $actual = (float) $item->unit_price;

                if ($target <= 0 || $actual <= 0) {
                    continue;
                }

                $errors[] = abs($actual - $target) / $target;
            }
        }

        if ($errors === []) {
            return ['value' => 0.0, 'meta' => ['sample_count' => 0]];
        }

        $mape = array_sum($errors) / count($errors);
        $accuracy = max(0, min(1, 1 - $mape));

        return ['value' => round($accuracy, 4), 'meta' => ['sample_count' => count($errors)]];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function normaliseSupplierPerformanceFilters(array $params): array
    {
        $start = isset($params['start_date'])
            ? Carbon::parse($params['start_date'])->startOfDay()
            : now()->copy()->subDays(90)->startOfDay();
        $end = isset($params['end_date'])
            ? Carbon::parse($params['end_date'])->endOfDay()
            : now()->copy()->endOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return ['start' => $start, 'end' => $end];
    }

    private function resolveSupplierBucket(Carbon $start, Carbon $end): string
    {
        return $start->diffInDays($end) > 90 ? 'monthly' : 'weekly';
    }

    /**
     * @return list<array{label: string, start: Carbon, end: Carbon}>
     */
    private function buildSupplierTimeBuckets(Carbon $start, Carbon $end, string $bucket): array
    {
        $buckets = [];
        $cursor = $start->copy()->startOfDay();

        while ($cursor <= $end) {
            $bucketStart = $cursor->copy();
            $bucketEnd = match ($bucket) {
                'monthly' => $bucketStart->copy()->endOfMonth(),
                default => $bucketStart->copy()->addDays(6),
            };

            if ($bucketEnd->gt($end)) {
                $bucketEnd = $end->copy();
            }

            $label = match ($bucket) {
                'monthly' => $bucketStart->format('Y-m'),
                default => sprintf('%s - %s', $bucketStart->toDateString(), $bucketEnd->toDateString()),
            };

            $buckets[] = [
                'label' => $label,
                'start' => $bucketStart,
                'end' => $bucketEnd,
            ];

            $cursor = match ($bucket) {
                'monthly' => $bucketStart->copy()->addMonth()->startOfMonth(),
                default => $bucketEnd->copy()->addDay(),
            };
        }

        return $buckets;
    }

    private function fetchSupplierReceiptRows(int $companyId, int $supplierId, Carbon $start, Carbon $end): Collection
    {
        return GoodsReceiptLine::query()
            ->select([
                'goods_receipt_lines.id',
                'goods_receipt_lines.received_qty',
                'goods_receipt_lines.rejected_qty',
                'po_lines.delivery_date',
                'po_lines.unit_price',
                'purchase_orders.ordered_at',
                'purchase_orders.expected_at',
                'goods_receipt_notes.inspected_at',
            ])
            ->join('goods_receipt_notes', 'goods_receipt_notes.id', '=', 'goods_receipt_lines.goods_receipt_note_id')
            ->join('po_lines', 'po_lines.id', '=', 'goods_receipt_lines.purchase_order_line_id')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'po_lines.purchase_order_id')
            ->where('goods_receipt_notes.company_id', $companyId)
            ->where('purchase_orders.company_id', $companyId)
            ->where('purchase_orders.supplier_id', $supplierId)
            ->whereBetween('goods_receipt_notes.inspected_at', [$start, $end])
            ->get();
    }

    private function fetchSupplierOrderRows(int $companyId, int $supplierId, Carbon $start, Carbon $end): Collection
    {
        return PurchaseOrder::query()
            ->select(['id', 'company_id', 'supplier_id', 'ordered_at', 'sent_at', 'acknowledged_at', 'created_at'])
            ->where('company_id', $companyId)
            ->where('supplier_id', $supplierId)
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('ordered_at', [$start, $end])
                    ->orWhereBetween('sent_at', [$start, $end])
                    ->orWhereBetween('created_at', [$start, $end]);
            })
            ->get();
    }

    /**
     * @param list<array{label: string, start: Carbon, end: Carbon}> $buckets
     */
    private function aggregateSupplierBuckets(array $buckets, Collection $receipts, Collection $orders): array
    {
        $stats = [];

        foreach ($buckets as $bucket) {
            $stats[$bucket['label']] = [
                'total_lines' => 0,
                'on_time_lines' => 0,
                'received_qty' => 0.0,
                'rejected_qty' => 0.0,
                'lead_time_sum' => 0.0,
                'lead_time_sum_sq' => 0.0,
                'lead_time_count' => 0,
                'price_sum' => 0.0,
                'price_sum_sq' => 0.0,
                'price_count' => 0,
                'response_sum_hours' => 0.0,
                'response_count' => 0,
            ];
        }

        foreach ($receipts as $row) {
            $inspectedAt = $this->carbonOrNull($row->inspected_at ?? null);
            if ($inspectedAt === null) {
                continue;
            }

            $label = $this->matchBucketLabel($buckets, $inspectedAt);
            if ($label === null) {
                continue;
            }

            $bucketStats = &$stats[$label];
            $bucketStats['total_lines']++;
            $bucketStats['received_qty'] += (float) ($row->received_qty ?? 0.0);
            $bucketStats['rejected_qty'] += (float) ($row->rejected_qty ?? 0.0);

            $expectedDate = $this->carbonOrNull($row->delivery_date ?? null)
                ?? $this->carbonOrNull($row->expected_at ?? null);
            if ($expectedDate !== null && $inspectedAt->lte($expectedDate)) {
                $bucketStats['on_time_lines']++;
            }

            $orderedAt = $this->carbonOrNull($row->ordered_at ?? null);
            if ($orderedAt !== null) {
                $leadTimeDays = max(0.0, $orderedAt->diffInHours($inspectedAt) / 24);
                $bucketStats['lead_time_sum'] += $leadTimeDays;
                $bucketStats['lead_time_sum_sq'] += $leadTimeDays ** 2;
                $bucketStats['lead_time_count']++;
            }

            $unitPrice = (float) ($row->unit_price ?? 0.0);
            if ($unitPrice > 0.0) {
                $bucketStats['price_sum'] += $unitPrice;
                $bucketStats['price_sum_sq'] += $unitPrice ** 2;
                $bucketStats['price_count']++;
            }
        }

        foreach ($orders as $order) {
            $eventDate = $this->carbonOrNull($order->ordered_at ?? null)
                ?? $this->carbonOrNull($order->sent_at ?? null)
                ?? $this->carbonOrNull($order->created_at ?? null);

            if ($eventDate === null) {
                continue;
            }

            $label = $this->matchBucketLabel($buckets, $eventDate);
            if ($label === null) {
                continue;
            }

            $acknowledgedAt = $this->carbonOrNull($order->acknowledged_at ?? null);
            if ($acknowledgedAt === null) {
                continue;
            }

            $hours = max(0.0, $eventDate->diffInHours($acknowledgedAt));
            $stats[$label]['response_sum_hours'] += $hours;
            $stats[$label]['response_count']++;
        }

        return $stats;
    }

    private function buildSupplierSeries(array $buckets, array $bucketStats): array
    {
        $series = [];

        $series[] = [
            'metric_name' => 'on_time_delivery_rate',
            'label' => 'On-Time Delivery Rate',
            'data' => $this->mapSupplierMetric($buckets, $bucketStats, function (array $stats): float {
                return $stats['total_lines'] > 0
                    ? round($stats['on_time_lines'] / $stats['total_lines'], 4)
                    : 0.0;
            }),
        ];

        $series[] = [
            'metric_name' => 'defect_rate',
            'label' => 'Defect Rate',
            'data' => $this->mapSupplierMetric($buckets, $bucketStats, function (array $stats): float {
                return $stats['received_qty'] > 0
                    ? round($stats['rejected_qty'] / $stats['received_qty'], 4)
                    : 0.0;
            }),
        ];

        $series[] = [
            'metric_name' => 'lead_time_variance',
            'label' => 'Lead-Time Std Dev (days)',
            'data' => $this->mapSupplierMetric($buckets, $bucketStats, function (array $stats): float {
                return $this->calculateStdDev($stats['lead_time_sum'], $stats['lead_time_sum_sq'], $stats['lead_time_count']);
            }),
        ];

        $series[] = [
            'metric_name' => 'price_volatility',
            'label' => 'Price Volatility',
            'data' => $this->mapSupplierMetric($buckets, $bucketStats, function (array $stats): float {
                // TODO: clarify with spec whether to weight volatility by spend instead of line counts.
                return $this->calculateStdDev($stats['price_sum'], $stats['price_sum_sq'], $stats['price_count']);
            }),
        ];

        $series[] = [
            'metric_name' => 'service_responsiveness',
            'label' => 'Service Responsiveness (hrs)',
            'data' => $this->mapSupplierMetric($buckets, $bucketStats, function (array $stats): float {
                return $stats['response_count'] > 0
                    ? round($stats['response_sum_hours'] / $stats['response_count'], 2)
                    : 0.0;
            }),
        ];

        return $series;
    }

    private function mapSupplierMetric(array $buckets, array $bucketStats, callable $callback): array
    {
        $data = [];

        foreach ($buckets as $bucket) {
            $label = $bucket['label'];
            $stats = $bucketStats[$label] ?? null;
            $data[] = [
                'date' => $label,
                'value' => $stats !== null ? $callback($stats) : 0.0,
            ];
        }

        return $data;
    }

    private function buildSupplierAggregates(array $bucketStats): array
    {
        $totals = [
            'total_lines' => 0,
            'on_time_lines' => 0,
            'received_qty' => 0.0,
            'rejected_qty' => 0.0,
            'lead_time_sum' => 0.0,
            'lead_time_sum_sq' => 0.0,
            'lead_time_count' => 0,
            'price_sum' => 0.0,
            'price_sum_sq' => 0.0,
            'price_count' => 0,
            'response_sum_hours' => 0.0,
            'response_count' => 0,
        ];

        foreach ($bucketStats as $stats) {
            foreach ($totals as $key => $_) {
                $totals[$key] += $stats[$key] ?? 0;
            }
        }

        return [
            'on_time_delivery_rate' => $totals['total_lines'] > 0
                ? round($totals['on_time_lines'] / $totals['total_lines'], 4)
                : 0.0,
            'defect_rate' => $totals['received_qty'] > 0
                ? round($totals['rejected_qty'] / $totals['received_qty'], 4)
                : 0.0,
            'lead_time_variance' => $this->calculateStdDev($totals['lead_time_sum'], $totals['lead_time_sum_sq'], $totals['lead_time_count']),
            'price_volatility' => $this->calculateStdDev($totals['price_sum'], $totals['price_sum_sq'], $totals['price_count']),
            'service_responsiveness' => $totals['response_count'] > 0
                ? round($totals['response_sum_hours'] / $totals['response_count'], 2)
                : 0.0,
        ];
    }

    private function emptySupplierPerformancePayload(int $supplierId, ?array $filtersUsed = null, ?string $supplierName = null): array
    {
        return [
            'series' => [],
            'table' => [[
                'supplier_id' => $supplierId,
                'supplier_name' => $supplierName,
                'on_time_delivery_rate' => 0.0,
                'defect_rate' => 0.0,
                'lead_time_variance' => 0.0,
                'price_volatility' => 0.0,
                'service_responsiveness' => 0.0,
                'risk_score' => null,
                'risk_category' => null,
            ]],
            'filters_used' => $filtersUsed,
        ];
    }

    private function calculateStdDev(float $sum, float $sumSq, int $count): float
    {
        if ($count <= 1) {
            return 0.0;
        }

        $mean = $sum / $count;
        $variance = max(0.0, ($sumSq / $count) - ($mean ** 2));

        return round(sqrt($variance), 4);
    }

    private function matchBucketLabel(array $buckets, Carbon $date): ?string
    {
        foreach ($buckets as $bucket) {
            if ($date->greaterThanOrEqualTo($bucket['start']) && $date->lessThanOrEqualTo($bucket['end'])) {
                return $bucket['label'];
            }
        }

        return null;
    }

    private function carbonOrNull($value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::parse($value->format('c'));
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return null;
    }

    /**
     * @return array{start: Carbon, end: Carbon, part_ids: array<int>, category_filters: array<int|string>, location_ids: array<int>}
     */
    private function normaliseForecastReportFilters(array $params): array
    {
        $start = isset($params['start_date'])
            ? Carbon::parse($params['start_date'])->startOfDay()
            : now()->copy()->subDays(90)->startOfDay();
        $end = isset($params['end_date'])
            ? Carbon::parse($params['end_date'])->endOfDay()
            : now()->copy()->endOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [
            'start' => $start,
            'end' => $end,
            'part_ids' => $this->normaliseIntegerList($params['part_ids'] ?? []),
            'category_filters' => $this->normaliseStringList($params['category_ids'] ?? []),
            'location_ids' => $this->normaliseIntegerList($params['location_ids'] ?? []),
        ];
    }

    private function resolveForecastBucket(Carbon $start, Carbon $end): string
    {
        return $start->diffInDays($end) > 60 ? 'weekly' : 'daily';
    }

    private function fetchActualUsageRows(
        int $companyId,
        Carbon $start,
        Carbon $end,
        array $partIds,
        array $locationIds
    ): Collection {
        $query = InventoryTxn::query()
            ->selectRaw('part_id, DATE(created_at) as usage_date, SUM(ABS(COALESCE(qty, 0))) as total_qty')
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('type', self::DEMAND_SIGNAL_TYPES);

        if ($partIds !== []) {
            $query->whereIn('part_id', $partIds);
        }

        if ($locationIds !== []) {
            $query->whereIn('warehouse_id', $locationIds);
        }

        return $query
            ->groupBy('part_id')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'))
            ->get();
    }

    private function fetchForecastSnapshotRows(int $companyId, Carbon $start, Carbon $end, array $partIds): Collection
    {
        $query = ForecastSnapshot::query()
            ->where('company_id', $companyId)
            ->whereBetween('period_start', [$start->toDateString(), $end->toDateString()])
            ->orderBy('period_start');

        if ($partIds !== []) {
            $query->whereIn('part_id', $partIds);
        }

        return $query->get();
    }

    /**
     * @param Collection<int, object> $rows
     * @return array<int, array<string, float>>
     */
    private function indexActualUsageRows(Collection $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $partId = (int) ($row->part_id ?? 0);

            if ($partId === 0) {
                continue;
            }

            $dateValue = $this->normaliseDateValue($row->usage_date ?? null);

            if ($dateValue === null) {
                continue;
            }

            $indexed[$partId][$dateValue] = round((float) ($row->total_qty ?? 0.0), 4);
        }

        return $indexed;
    }

    /**
     * @param Collection<int, ForecastSnapshot> $rows
     * @return array{0: array<int, array<string, float>>, 1: array<int, array<string, float>>}
     */
    private function indexForecastSnapshotRows(Collection $rows): array
    {
        $daily = [];
        $latest = [];

        foreach ($rows as $snapshot) {
            $partId = (int) ($snapshot->part_id ?? 0);

            if ($partId === 0) {
                continue;
            }

            $dateValue = $this->normaliseDateValue($snapshot->period_start ?? null);

            if ($dateValue === null) {
                continue;
            }

            $avgDaily = (float) ($snapshot->avg_daily_demand ?? 0.0);

            if ($avgDaily <= 0.0 && (float) ($snapshot->demand_qty ?? 0.0) > 0.0) {
                $horizonDays = max(1, (int) ($snapshot->horizon_days ?? 1));
                $avgDaily = (float) $snapshot->demand_qty / $horizonDays;
            }

            $avgDaily = round(max($avgDaily, 0.0), 4);

            $daily[$partId][$dateValue] = $avgDaily;
            $latest[$partId] = [
                'avg_daily_demand' => $avgDaily,
                'safety_stock_qty' => (float) ($snapshot->safety_stock_qty ?? 0.0),
                'on_hand_qty' => (float) ($snapshot->on_hand_qty ?? 0.0),
                'on_order_qty' => (float) ($snapshot->on_order_qty ?? 0.0),
            ];
        }

        return [$daily, $latest];
    }

    /**
     * @param array<int> $requested
     * @param array<int, array<string, float>> $actual
     * @param array<int, array<string, float>> $forecast
     * @return array<int>
     */
    private function resolveCandidatePartIds(array $requested, array $actual, array $forecast): array
    {
        if ($requested !== []) {
            return $requested;
        }

        $partIds = array_unique(array_merge(array_keys($actual), array_keys($forecast)));

        return array_values(array_filter($partIds, static fn ($value) => (int) $value > 0));
    }

    private function loadPartMetadataForForecast(int $companyId, array $partIds, array $categoryFilters): Collection
    {
        if ($partIds === []) {
            return collect();
        }

        $query = Part::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $partIds)
            ->with('inventorySetting')
            ->orderBy('name');

        if ($categoryFilters !== []) {
            $query->whereIn('category', $categoryFilters);
        }

        return $query->get()->keyBy('id');
    }

    private function emptyForecastAggregates(): array
    {
        return [
            'total_forecast' => 0.0,
            'total_actual' => 0.0,
            'mape' => 0.0,
            'mae' => 0.0,
            'avg_daily_demand' => 0.0,
            'recommended_reorder_point' => 0.0,
            'recommended_safety_stock' => 0.0,
        ];
    }

    private function buildForecastAggregates(array $totals): array
    {
        $mae = $totals['abs_error_count'] > 0
            ? round($totals['abs_error_sum'] / $totals['abs_error_count'], 4)
            : 0.0;
        $mape = $totals['pct_error_count'] > 0
            ? round($totals['pct_error_sum'] / $totals['pct_error_count'], 4)
            : 0.0;

        return [
            'total_forecast' => round($totals['total_forecast'], 3),
            'total_actual' => round($totals['total_actual'], 3),
            'mape' => $mape,
            'mae' => $mae,
            'avg_daily_demand' => $totals['day_count'] > 0
                ? round($totals['total_actual'] / $totals['day_count'], 4)
                : 0.0,
            'recommended_reorder_point' => $this->averageArray($totals['reorder_points']),
            'recommended_safety_stock' => $this->averageArray($totals['safety_stocks']),
        ];
    }

    /**
     * @return list<array{label: string, start: Carbon, end: Carbon}>
     */
    private function buildTimeBuckets(Carbon $start, Carbon $end, string $bucket): array
    {
        $buckets = [];
        $cursor = $start->copy();

        while ($cursor <= $end) {
            $bucketStart = $cursor->copy();
            $bucketEnd = $bucket === 'weekly'
                ? $bucketStart->copy()->addDays(6)
                : $bucketStart->copy();

            if ($bucketEnd->gt($end)) {
                $bucketEnd = $end->copy();
            }

            $buckets[] = [
                'label' => $bucket === 'weekly'
                    ? sprintf('%s - %s', $bucketStart->toDateString(), $bucketEnd->toDateString())
                    : $bucketStart->toDateString(),
                'start' => $bucketStart,
                'end' => $bucketEnd,
            ];

            $cursor = $bucketEnd->copy()->addDay();
        }

        return $buckets;
    }

    /**
     * @param array<string, float> $actualDaily
     * @param array<string, float> $forecastDaily
     * @return array{0: list<array{date: string, actual: float, forecast: float}>, 1: array<string, float|int>}
     */
    private function buildPartForecastSeries(array $buckets, array $actualDaily, array $forecastDaily): array
    {
        $series = [];
        $totalActual = 0.0;
        $totalForecast = 0.0;
        $absErrorSum = 0.0;
        $absErrorCount = 0;
        $pctErrorSum = 0.0;
        $pctErrorCount = 0;
        $dayCount = 0;
        $carryForecast = null;

        foreach ($buckets as $bucket) {
            [$bucketActual, $bucketForecast, $carryForecast, $bucketDays] = $this->summariseBucket(
                $bucket['start']->copy(),
                $bucket['end']->copy(),
                $actualDaily,
                $forecastDaily,
                $carryForecast
            );

            $series[] = [
                'date' => $bucket['label'],
                'actual' => round($bucketActual, 3),
                'forecast' => round($bucketForecast, 3),
            ];

            $totalActual += $bucketActual;
            $totalForecast += $bucketForecast;
            $absDifference = abs($bucketActual - $bucketForecast);
            $absErrorSum += $absDifference;
            $absErrorCount++;

            if ($bucketForecast > 0.0) {
                $pctErrorSum += $absDifference / $bucketForecast;
                $pctErrorCount++;
            }

            $dayCount += $bucketDays;
        }

        $mae = $absErrorCount > 0 ? round($absErrorSum / $absErrorCount, 4) : 0.0;
        $mape = $pctErrorCount > 0 ? round($pctErrorSum / $pctErrorCount, 4) : 0.0;
        $avgDailyDemand = $dayCount > 0 ? round($totalActual / $dayCount, 4) : 0.0;

        return [
            $series,
            [
                'total_forecast' => round($totalForecast, 4),
                'total_actual' => round($totalActual, 4),
                'mae' => $mae,
                'mape' => $mape,
                'avg_daily_demand' => $avgDailyDemand,
                'day_count' => $dayCount,
                'abs_error_sum' => $absErrorSum,
                'abs_error_count' => $absErrorCount,
                'pct_error_sum' => $pctErrorSum,
                'pct_error_count' => $pctErrorCount,
            ],
        ];
    }

    /**
     * @return array{0: float, 1: float, 2: ?float, 3: int}
     */
    private function summariseBucket(
        Carbon $bucketStart,
        Carbon $bucketEnd,
        array $actualDaily,
        array $forecastDaily,
        ?float $carryForecast
    ): array {
        $bucketActual = 0.0;
        $bucketForecast = 0.0;
        $bucketDays = 0;

        $cursor = $bucketStart->copy();

        while ($cursor <= $bucketEnd) {
            $dateKey = $cursor->toDateString();
            $bucketActual += (float) ($actualDaily[$dateKey] ?? 0.0);

            if (array_key_exists($dateKey, $forecastDaily)) {
                $carryForecast = (float) $forecastDaily[$dateKey];
            }

            if ($carryForecast !== null) {
                $bucketForecast += $carryForecast;
            }

            $cursor->addDay();
            $bucketDays++;
        }

        return [$bucketActual, $bucketForecast, $carryForecast, $bucketDays];
    }

    private function calculateReorderRecommendation(Part $part, ?array $snapshotMeta, float $avgDailyDemand): array
    {
        $inventorySetting = $part->inventorySetting;
        $meta = is_array($part->meta) ? $part->meta : [];
        $leadTimeDays = (int) ($inventorySetting?->lead_time_days ?? ($meta['lead_time_days'] ?? 7));
        $leadTimeDays = max($leadTimeDays, 1);

        $safetyStockCandidates = [
            $snapshotMeta['safety_stock_qty'] ?? null,
            $inventorySetting?->safety_stock,
            $inventorySetting?->min_qty,
        ];

        $safetyStock = 0.0;

        foreach ($safetyStockCandidates as $candidate) {
            if ($candidate === null) {
                continue;
            }

            $safetyStock = max($safetyStock, (float) $candidate);
        }

        if ($avgDailyDemand <= 0.0) {
            $avgDailyDemand = (float) ($snapshotMeta['avg_daily_demand'] ?? 0.0);
        }

        $reorderPoint = ($avgDailyDemand * $leadTimeDays) + $safetyStock;

        if ($inventorySetting?->reorder_qty !== null) {
            $reorderPoint = max($reorderPoint, (float) $inventorySetting->reorder_qty);
        }

        return [
            'reorder_point' => round(max($reorderPoint, 0.0), 3),
            'safety_stock' => round(max($safetyStock, 0.0), 3),
        ];
    }

    /**
     * @param mixed $values
     * @return array<int>
     */
    private function normaliseIntegerList($values): array
    {
        $items = is_array($values) ? $values : [$values];

        return collect($items)
            ->filter(static fn ($value) => $value !== null && $value !== '')
            ->map(static fn ($value) => (int) $value)
            ->filter(static fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param mixed $values
     * @return array<int|string>
     */
    private function normaliseStringList($values): array
    {
        $items = is_array($values) ? $values : [$values];

        return collect($items)
            ->filter(static fn ($value) => is_string($value) && trim($value) !== '')
            ->map(static fn (string $value) => trim($value))
            ->unique()
            ->values()
            ->all();
    }

    private function normaliseDateValue($value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value)->toDateString();
        }

        return null;
    }

    /**
     * @param array<float> $values
     */
    private function averageArray(array $values): float
    {
        $count = 0;
        $sum = 0.0;

        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $count++;
            $sum += (float) $value;
        }

        return $count > 0 ? round($sum / $count, 3) : 0.0;
    }
}
