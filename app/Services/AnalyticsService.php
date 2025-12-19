<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\AnalyticsSnapshot;
use App\Models\Company;
use App\Models\GoodsReceiptLine;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\RFQ;
use App\Support\Audit\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AnalyticsService
{
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
}
