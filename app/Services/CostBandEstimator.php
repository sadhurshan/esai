<?php

namespace App\Services;

use App\Models\PricingObservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CostBandEstimator
{
    private const WINDOW_MONTHS = 24;
    private const MIN_SAMPLES = 5;
    private const MAX_SAMPLES = 250;

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>|null
     */
    public function estimateForFilters(array $filters, int $companyId): ?array
    {
        $criteria = $this->normalizeCriteria($filters);

        $query = PricingObservation::query()
            ->where('company_id', $companyId)
            ->whereNotNull('unit_price_minor')
            ->whereNotNull('currency')
            ->where('observed_at', '>=', now()->subMonths(self::WINDOW_MONTHS));

        foreach (['process', 'material', 'finish', 'region'] as $field) {
            if (! empty($criteria[$field])) {
                $query->where($field, $criteria[$field]);
            }
        }

        $observations = $query
            ->orderByDesc('observed_at')
            ->limit(self::MAX_SAMPLES)
            ->get(['unit_price_minor', 'currency']);

        if ($observations->isEmpty()) {
            return $this->insufficientData($criteria, 0);
        }

        $currency = $this->resolvePrimaryCurrency($observations);
        $values = $observations
            ->filter(static fn ($obs) => $obs->currency === $currency)
            ->pluck('unit_price_minor')
            ->filter()
            ->map(static fn ($value) => (int) $value)
            ->sort()
            ->values()
            ->all();

        $sampleSize = count($values);

        if ($sampleSize < self::MIN_SAMPLES) {
            return $this->insufficientData($criteria, $sampleSize);
        }

        $min = $this->percentile($values, 0.2);
        $max = $this->percentile($values, 0.8);

        if ($min === null || $max === null) {
            return $this->insufficientData($criteria, $sampleSize);
        }

        return [
            'status' => 'estimated',
            'min_minor' => $min,
            'max_minor' => $max,
            'currency' => $currency,
            'sample_size' => $sampleSize,
            'period_months' => self::WINDOW_MONTHS,
            'matched_on' => $criteria,
            'explanation' => $this->buildExplanation($criteria, $sampleSize),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{process:?string, material:?string, finish:?string, region:?string}
     */
    private function normalizeCriteria(array $filters): array
    {
        return [
            'process' => $this->normalizeProcess($filters['capability'] ?? null),
            'material' => $this->normalizeValue($filters['material'] ?? null),
            'finish' => $this->normalizeValue($filters['finish'] ?? null),
            'region' => $this->normalizeValue($filters['location'] ?? null),
        ];
    }

    private function normalizeValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::of($value)->lower()->trim()->value();

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeProcess(?string $value): ?string
    {
        $normalized = $this->normalizeValue($value);

        if ($normalized === null) {
            return null;
        }

        return match ($normalized) {
            'cnc', 'cnc milling', 'cnc turning' => 'cnc',
            'sheet metal', 'sheet_metal' => 'sheet_metal',
            'injection molding', 'injection_molding' => 'injection_molding',
            'additive', '3d printing', '3d_printing' => '3d_printing',
            default => $normalized,
        };
    }

    private function resolvePrimaryCurrency(Collection $observations): ?string
    {
        return $observations
            ->filter(static fn ($obs) => $obs->currency !== null)
            ->countBy(static fn ($obs) => (string) $obs->currency)
            ->sortDesc()
            ->keys()
            ->first();
    }

    /**
     * @param array<int, int> $values
     */
    private function percentile(array $values, float $percentile): ?int
    {
        $count = count($values);

        if ($count === 0) {
            return null;
        }

        $index = (int) round(($count - 1) * $percentile);

        return $values[$index] ?? null;
    }

    /**
     * @param array<string, mixed> $criteria
     */
    private function insufficientData(array $criteria, int $sampleSize): array
    {
        return [
            'status' => 'insufficient_data',
            'min_minor' => null,
            'max_minor' => null,
            'currency' => null,
            'sample_size' => $sampleSize,
            'period_months' => self::WINDOW_MONTHS,
            'matched_on' => $criteria,
            'explanation' => 'Not enough historical pricing data to estimate a cost band for the current filters.',
        ];
    }

    /**
     * @param array<string, mixed> $criteria
     */
    private function buildExplanation(array $criteria, int $sampleSize): string
    {
        $parts = [];

        if (! empty($criteria['process'])) {
            $parts[] = sprintf('process "%s"', $criteria['process']);
        }

        if (! empty($criteria['material'])) {
            $parts[] = sprintf('material "%s"', $criteria['material']);
        }

        if (! empty($criteria['finish'])) {
            $parts[] = sprintf('finish "%s"', $criteria['finish']);
        }

        if (! empty($criteria['region'])) {
            $parts[] = sprintf('region "%s"', $criteria['region']);
        }

        $criteriaText = $parts === [] ? 'your current filters' : implode(', ', $parts);

        return sprintf(
            'Based on %d historical quotes from the last %d months matching %s.',
            $sampleSize,
            self::WINDOW_MONTHS,
            $criteriaText
        );
    }
}
