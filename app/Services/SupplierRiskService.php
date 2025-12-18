<?php

namespace App\Services;

use App\Enums\RiskGrade;
use App\Enums\RmaStatus;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\GoodsReceiptLine;
use App\Models\Quote;
use App\Models\RfqInvitation;
use App\Models\Rma;
use App\Models\Supplier;
use App\Models\SupplierRiskScore;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SupplierRiskService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function calculateForSupplier(Supplier $supplier, Carbon $periodStart, ?Carbon $periodEnd = null): SupplierRiskScore
    {
        $supplier->loadMissing('company');

        $company = $supplier->company;

        if (! $company instanceof Company) {
            throw new InvalidArgumentException('Supplier must belong to a company.');
        }

        $periodStart = $periodStart->copy()->startOfDay();
        $periodEnd = ($periodEnd ?? $periodStart->copy()->endOfMonth())->copy()->endOfDay();
        $periodKey = $periodStart->format('Y-m');

        $goodsReceiptLines = GoodsReceiptLine::query()
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->whereHas('purchaseOrderLine.purchaseOrder', function ($query) use ($supplier): void {
                $query->whereHas('quote', function ($quoteQuery) use ($supplier): void {
                    $quoteQuery->where('supplier_id', $supplier->id);
                });
            })
            ->with(['goodsReceiptNote', 'purchaseOrderLine.purchaseOrder'])
            ->get();

        $deliveredLines = 0;
        $onTimeLines = 0;
        $lateLines = 0;
        $totalReceivedQty = 0;
        $totalRejectedQty = 0;
        $leadTimeDeltas = [];

        foreach ($goodsReceiptLines as $line) {
            $receivedQty = (int) $line->received_qty;

            if ($receivedQty <= 0) {
                continue;
            }

            ++$deliveredLines;

            $note = $line->goodsReceiptNote;
            $actualDate = $note?->inspected_at ?? $note?->created_at ?? $line->created_at;
            $poLine = $line->purchaseOrderLine;
            $promisedDate = $poLine?->delivery_date;

            if ($actualDate !== null && $promisedDate !== null) {
                $actual = Carbon::parse($actualDate)->startOfDay();
                $promised = Carbon::parse($promisedDate)->endOfDay();

                if ($actual->lte($promised)) {
                    ++$onTimeLines;
                } else {
                    ++$lateLines;
                }
            }

            $purchaseOrder = $poLine?->purchaseOrder;
            if ($purchaseOrder && $actualDate !== null && $promisedDate !== null) {
                $orderCreated = $purchaseOrder->created_at ? Carbon::parse($purchaseOrder->created_at) : null;

                if ($orderCreated !== null) {
                    $promisedLead = max(1, $orderCreated->diffInDays(Carbon::parse($promisedDate)));
                    $actualLead = max(1, $orderCreated->diffInDays(Carbon::parse($actualDate)));
                    $leadTimeDeltas[] = abs($actualLead - $promisedLead) / max(1, $promisedLead);
                }
            }

            $totalReceivedQty += $receivedQty;
            $totalRejectedQty += (int) $line->rejected_qty;
        }

        $rmaSummary = $this->summarizeRmaDefects($supplier, $periodStart, $periodEnd);
        $totalRejectedQty += $rmaSummary['defect_units'];

        $creditSummary = $this->summarizeCreditNoteImpact($supplier, $periodStart, $periodEnd);
        $creditPenaltyUnits = $creditSummary['amount_minor'] > 0
            ? max($creditSummary['count'], (int) ceil($creditSummary['amount_minor'] / 10000))
            : $creditSummary['count'];
        $totalRejectedQty += $creditPenaltyUnits;

        $onTimeRate = $deliveredLines > 0 ? $this->roundToScale($onTimeLines / $deliveredLines) : null;
        $defectRate = $totalReceivedQty > 0 ? $this->roundToScale($totalRejectedQty / $totalReceivedQty) : null;
        $leadTimeVolatility = $this->roundToVolatility($this->coefficientOfVariation($leadTimeDeltas));

        $quotes = Quote::query()
            ->where('supplier_id', $supplier->id)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->whereIn('status', ['awarded', 'accepted', 'confirmed'])
            ->with(['rfq.items'])
            ->get();

        $priceRatios = [];

        foreach ($quotes as $quote) {
            $targets = $quote->rfq?->items;
            $targetPrice = $targets instanceof Collection ? (float) $targets->avg('target_price') : 0.0;

            if ($targetPrice <= 0) {
                continue;
            }

            $priceRatios[] = abs((float) $quote->unit_price - $targetPrice) / $targetPrice;
        }

        $priceVolatility = $this->roundToVolatility($this->coefficientOfVariation($priceRatios));

        $invitationsCount = RfqInvitation::query()
            ->where('supplier_id', $supplier->id)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->count();

        $quotesResponded = Quote::query()
            ->where('supplier_id', $supplier->id)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->count();

        $responsivenessRate = $invitationsCount > 0
            ? $this->roundToScale($quotesResponded / $invitationsCount)
            : ($quotesResponded > 0 ? 1.0 : null);

        $riskInputs = [
            'delivery' => $this->normalizeRisk($onTimeRate, invert: true),
            'defect' => $this->normalizeRisk($defectRate),
            'price' => $this->normalizeRisk($priceVolatility),
            'lead_time' => $this->normalizeRisk($leadTimeVolatility),
            'responsiveness' => $this->normalizeRisk($responsivenessRate, invert: true),
        ];

        $weights = [
            'delivery' => 0.3,
            'defect' => 0.2,
            'price' => 0.2,
            'lead_time' => 0.2,
            'responsiveness' => 0.1,
        ];

        $overallScore = 0.0;

        foreach ($weights as $key => $weight) {
            $overallScore += $riskInputs[$key] * $weight;
        }

        $overallScore = $this->roundToVolatility($overallScore);

        $grade = match (true) {
            $overallScore <= 0.33 => RiskGrade::Low,
            $overallScore <= 0.66 => RiskGrade::Medium,
            default => RiskGrade::High,
        };

        $badges = $this->buildBadges(
            $onTimeRate,
            $lateLines,
            $defectRate,
            $priceVolatility,
            $leadTimeVolatility,
            $responsivenessRate,
            $rmaSummary['count'],
            $creditSummary['count'],
        );

        return DB::transaction(function () use (
            $company,
            $supplier,
            $periodStart,
            $periodEnd,
            $periodKey,
            $onTimeRate,
            $defectRate,
            $priceVolatility,
            $leadTimeVolatility,
            $responsivenessRate,
            $overallScore,
            $grade,
            $badges,
        ): SupplierRiskScore {
            $attributes = [
                'company_id' => $company->id,
                'supplier_id' => $supplier->id,
            ];

            $payload = [
                'on_time_delivery_rate' => $onTimeRate,
                'defect_rate' => $defectRate,
                'price_volatility' => $priceVolatility,
                'lead_time_volatility' => $leadTimeVolatility,
                'responsiveness_rate' => $responsivenessRate,
                'overall_score' => $overallScore,
                'risk_grade' => $grade->value,
                'badges_json' => $badges,
                'meta' => [
                    'period_key' => $periodKey,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                ],
            ];

            $score = SupplierRiskScore::firstOrNew($attributes);
            $before = $score->exists ? Arr::only($score->toArray(), array_keys($payload)) : [];

            $score->fill($payload);
            $dirty = array_keys($score->getDirty());
            $score->save();

            if ($score->wasRecentlyCreated) {
                $this->auditLogger->created($score, Arr::only($score->toArray(), array_keys($payload)));
            } elseif (! empty($dirty)) {
                $fresh = Arr::only($score->refresh()->toArray(), array_keys($payload));
                $this->auditLogger->updated($score, Arr::only($before, $dirty), Arr::only($fresh, $dirty));
            }

            $supplierBefore = ['risk_grade' => $supplier->risk_grade?->value];
            $supplier->risk_grade = $grade;

            if ($supplier->isDirty('risk_grade')) {
                $supplier->save();
                $this->auditLogger->updated($supplier, $supplierBefore, ['risk_grade' => $grade->value]);
            }

            return $score->fresh();
        });
    }

    /**
     * @return Collection<int, SupplierRiskScore>
     */
    public function calculateForCompany(Company $company, Carbon $periodStart, ?Carbon $periodEnd = null): Collection
    {
        $periodStart = $periodStart->copy()->startOfDay();
        $periodEnd = ($periodEnd ?? $periodStart->copy()->endOfMonth())->copy()->endOfDay();
        $periodKey = $periodStart->format('Y-m');

        $existingForPeriod = SupplierRiskScore::query()
            ->where('company_id', $company->id)
            ->where('meta->period_key', $periodKey)
            ->exists();

        $scores = $company->suppliers()
            ->get()
            ->map(fn (Supplier $supplier): SupplierRiskScore => $this->calculateForSupplier($supplier, $periodStart, $periodEnd));

        if (! $existingForPeriod && $scores->isNotEmpty()) {
            $company->increment('risk_scores_monthly_used');
        }

        return $scores;
    }

    /**
     * @return array{count:int, defect_units:int}
     */
    private function summarizeRmaDefects(Supplier $supplier, Carbon $periodStart, Carbon $periodEnd): array
    {
        $query = Rma::query()
            ->where('company_id', $supplier->company_id)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->whereIn('status', [RmaStatus::Approved->value, RmaStatus::Closed->value])
            ->whereHas('purchaseOrder', function (Builder $builder) use ($supplier): void {
                $builder->where('supplier_id', $supplier->id);
            });

        $records = $query->get(['defect_qty']);

        $defectUnits = $records->sum(static function (Rma $rma): int {
            $quantity = $rma->defect_qty ?? 1;

            return max(1, (int) $quantity);
        });

        return [
            'count' => $records->count(),
            'defect_units' => max(0, $defectUnits),
        ];
    }

    /**
     * @return array{count:int, amount_minor:int}
     */
    private function summarizeCreditNoteImpact(Supplier $supplier, Carbon $periodStart, Carbon $periodEnd): array
    {
        $query = CreditNote::query()
            ->where('company_id', $supplier->company_id)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->whereHas('purchaseOrder', function (Builder $builder) use ($supplier): void {
                $builder->where('supplier_id', $supplier->id);
            });

        return [
            'count' => $query->count(),
            'amount_minor' => (int) $query->sum(DB::raw('COALESCE(amount_minor, 0)')),
        ];
    }

    private function coefficientOfVariation(array $values): float
    {
        $values = array_values(array_filter($values, static fn ($value) => $value !== null));

        if (count($values) <= 1) {
            return 0.0;
        }

        $mean = array_sum($values) / count($values);

        if ($mean == 0.0) {
            return 0.0;
        }

        $variance = array_sum(array_map(static fn ($value) => ($value - $mean) ** 2, $values)) / count($values);

        return sqrt($variance) / abs($mean);
    }

    private function normalizeRisk(?float $value, bool $invert = false): float
    {
        if ($value === null) {
            return 0.5;
        }

        $clamped = max(0.0, min(1.0, $value));

        return $invert ? 1 - $clamped : $clamped;
    }

    private function roundToScale(?float $value): ?float
    {
        return $value === null ? null : round(max(0.0, min(1.0, $value)), 4);
    }

    private function roundToVolatility(?float $value): float
    {
        $value ??= 0.0;

        return round(max(0.0, min(1.0, $value)), 4);
    }

    private function buildBadges(
        ?float $onTimeRate,
        int $lateLines,
        ?float $defectRate,
        float $priceVolatility,
        float $leadTimeVolatility,
        ?float $responsivenessRate,
        int $rmaCount,
        int $creditNoteCount
    ): array
    {
        $badges = [];

        if ($onTimeRate !== null && $onTimeRate < 0.85) {
            $badges[] = sprintf('%d late POs', max(1, $lateLines));
        }

        if ($defectRate !== null && $defectRate > 0.1) {
            $badges[] = 'Elevated defect rate';
        }

        if ($rmaCount > 0) {
            $badges[] = sprintf('%d RMA%s logged', $rmaCount, $rmaCount === 1 ? '' : 's');
        }

        if ($creditNoteCount > 0) {
            $badges[] = sprintf('%d credit note%s issued', $creditNoteCount, $creditNoteCount === 1 ? '' : 's');
        }

        if ($priceVolatility > 0.2) {
            $badges[] = 'Price swings observed';
        }

        if ($leadTimeVolatility > 0.2) {
            $badges[] = 'Lead time variance high';
        }

        if ($responsivenessRate !== null && $responsivenessRate < 0.6) {
            $badges[] = 'Slow RFQ responses';
        }

        if (empty($badges)) {
            $badges[] = 'Performance stable';
        }

        return array_values(array_unique($badges));
    }
}
