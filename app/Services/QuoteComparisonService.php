<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Quote;
use App\Models\RFQ;
use Illuminate\Support\Collection;

class QuoteComparisonService
{
    /**
     * @return Collection<int, array{quote: Quote, scores: array{price: float, lead_time: float, rating: float, composite: float, rank: int}}>
     */
    public function build(RFQ $rfq): Collection
    {
        $quotes = $rfq->quotes()
            ->with([
                'supplier.company',
                'supplier.riskScore',
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

        $results = $quotes->map(function (Quote $quote) use ($minTotal, $minLead): array {
            $totalMinor = (int) ($quote->total_price_minor ?? 0);
            $lead = $quote->lead_time_days ?? 0;
            $riskScore = (float) ($quote->supplier?->riskScore?->overall_score ?? 0);

            $priceScore = $this->normalizeBenefit($minTotal, $totalMinor);
            $leadScore = $this->normalizeBenefit($minLead, $lead);
            $ratingScore = $this->normalizeClamp($riskScore);

            // TODO: clarify weighting with product team once scoring spec is finalized.
            $composite = round(($priceScore * 0.5) + ($leadScore * 0.3) + ($ratingScore * 0.2), 4);

            return [
                'quote' => $quote,
                'scores' => [
                    'price' => $priceScore,
                    'lead_time' => $leadScore,
                    'rating' => $ratingScore,
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
