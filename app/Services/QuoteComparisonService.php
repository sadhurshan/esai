<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Quote;
use App\Models\RFQ;
use Illuminate\Support\Collection;

class QuoteComparisonService
{
    public function __construct(private readonly SupplierFitService $fitService)
    {
    }

    /**
     * @return Collection<int, array{quote: Quote, scores: array{price: float, lead_time: float, risk: float, fit: float, composite: float, rank: int}}>
     */
    public function build(RFQ $rfq): Collection
    {
        $rfq->loadMissing('items');

        $quotes = $rfq->quotes()
            ->with([
                'supplier' => function ($query): void {
                    $query->withoutGlobalScope('company_scope')
                        ->with([
                            'company:id,name,supplier_status,is_verified,verified_at',
                            'riskScore' => static fn ($riskQuery) => $riskQuery->withoutGlobalScope('company_scope'),
                            'documents' => function ($documentQuery): void {
                                $documentQuery
                                    ->withoutGlobalScope('company_scope')
                                    ->select(['id', 'supplier_id', 'type', 'status', 'expires_at'])
                                    ->orderBy('expires_at')
                                    ->limit(10);
                            },
                        ])
                        ->withCount([
                            'documents as valid_documents_count' => static fn ($docQuery) => $docQuery
                                ->withoutGlobalScope('company_scope')
                                ->where('status', 'valid'),
                            'documents as expiring_documents_count' => static fn ($docQuery) => $docQuery
                                ->withoutGlobalScope('company_scope')
                                ->where('status', 'expiring'),
                            'documents as expired_documents_count' => static fn ($docQuery) => $docQuery
                                ->withoutGlobalScope('company_scope')
                                ->where('status', 'expired'),
                        ]);
                },
                'items.rfqItem',
                'items.taxes.taxCode',
                'documents',
            ])
            ->whereNull('withdrawn_at')
            ->whereNotIn('status', ['withdrawn'])
            ->get();

        $minTotal = $quotes
            ->map(static fn (Quote $quote): int => (int) ($quote->total_price_minor ?? 0))
            ->reject(static fn (int $value): bool => $value <= 0)
            ->min() ?? 0;

        $minLead = $quotes
            ->map(static fn (Quote $quote): ?int => $quote->lead_time_days)
            ->reject(static fn (?int $value): bool => $value === null || $value <= 0)
            ->min() ?? 0;

        $results = $quotes->map(function (Quote $quote) use ($rfq, $minTotal, $minLead): array {
            $totalMinor = (int) ($quote->total_price_minor ?? 0);
            $lead = $quote->lead_time_days ?? 0;
            $riskScore = (float) ($quote->supplier?->riskScore?->overall_score ?? 0);
            $fitResult = $this->fitService->score($rfq, $quote);
            $fitScore = $fitResult['score'];

            $priceScore = $this->normalizeBenefit($minTotal, $totalMinor);
            $leadScore = $this->normalizeBenefit($minLead, $lead);
            $normalizedRisk = $this->normalizeClamp($riskScore);

            $composite = round((
                $priceScore
                + $leadScore
                + $normalizedRisk
                + $fitScore
            ) / 4, 4);

            return [
                'quote' => $quote,
                'scores' => [
                    'price' => $priceScore,
                    'lead_time' => $leadScore,
                    'risk' => $normalizedRisk,
                    'fit' => $fitScore,
                    'composite' => $composite,
                    'rank' => 0,
                ],
            ];
        })->sortByDesc(static fn (array $row): float => $row['scores']['composite'])->values();

        $rank = 1;
        $previousScore = null;

        foreach ($results as $index => $row) {
            $currentScore = $row['scores']['composite'];
            if ($previousScore !== null && abs($currentScore - $previousScore) > 0.0001) {
                $rank++;
            }

            $row['scores']['rank'] = $rank;
            $results[$index] = $row;
            $previousScore = $currentScore;
        }

        return $results;
    }

    private function normalizeBenefit(int $bestValue, int $currentValue): float
    {
        if ($bestValue <= 0 || $currentValue <= 0) {
            return 0.0;
        }

        $score = $bestValue / $currentValue;

        return round(min(max($score, 0), 1), 4);
    }

    private function normalizeClamp(float $value): float
    {
        if ($value < 0) {
            return 0.0;
        }

        if ($value > 1) {
            return 1.0;
        }

        return round($value, 4);
    }
}
