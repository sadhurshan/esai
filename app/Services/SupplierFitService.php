<?php

namespace App\Services;

use App\Models\RFQ;
use App\Models\Quote;
use App\Models\Supplier;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SupplierFitService
{
    private const CAPABILITY_DIMENSIONS = ['methods', 'materials', 'finishes', 'tolerances'];
    private const PRICE_BAND_RANKS = [
        'budget' => 1,
        'low' => 1,
        'tier_1' => 1,
        'tier1' => 1,
        'standard' => 2,
        'mid' => 2,
        'tier_2' => 2,
        'tier2' => 2,
        'premium' => 3,
        'high' => 3,
        'tier_3' => 3,
        'tier3' => 3,
    ];

    /**
     * @return array{score: float, dimensions: array{capabilities: float, geo: float, price_band: float}}
     */
    public function score(RFQ $rfq, ?Quote $quote = null): array
    {
        $rfq->loadMissing('items');
        $supplier = $quote?->supplier;

        if (! $supplier instanceof Supplier) {
            return [
                'score' => 0.0,
                'dimensions' => [
                    'capabilities' => 0.0,
                    'geo' => 0.0,
                    'price_band' => 0.0,
                ],
            ];
        }

        $capabilityScore = $this->capabilityOverlapScore($rfq, $supplier);
        $geoScore = $this->geoMatchScore($rfq, $supplier);
        $priceBandScore = $this->priceBandScore($supplier);

        $composite = round((
            ($capabilityScore * 0.6)
            + ($geoScore * 0.25)
            + ($priceBandScore * 0.15)
        ), 4);

        return [
            'score' => $composite,
            'dimensions' => [
                'capabilities' => $capabilityScore,
                'geo' => $geoScore,
                'price_band' => $priceBandScore,
            ],
        ];
    }

    private function capabilityOverlapScore(RFQ $rfq, Supplier $supplier): float
    {
        $requirements = $this->rfqCapabilityMatrix($rfq);
        $supplierCapabilities = $this->supplierCapabilityMatrix($supplier);

        $dimensionScores = [];

        foreach (self::CAPABILITY_DIMENSIONS as $dimension) {
            $required = $requirements[$dimension];

            if ($required === []) {
                continue;
            }

            $supplierValues = $supplierCapabilities[$dimension];

            if ($supplierValues === []) {
                $dimensionScores[] = 0.0;
                continue;
            }

            $matches = 0;

            foreach ($required as $value) {
                if ($this->hasCapabilityMatch($value, $supplierValues)) {
                    ++$matches;
                }
            }

            $dimensionScores[] = $matches / count($required);
        }

        if ($dimensionScores === []) {
            // No explicit requirements → neutral baseline until RFQ specs improve.
            return 0.5;
        }

        return round(array_sum($dimensionScores) / count($dimensionScores), 4);
    }

    private function rfqCapabilityMatrix(RFQ $rfq): array
    {
        $matrix = [];

        foreach (self::CAPABILITY_DIMENSIONS as $dimension) {
            $matrix[$dimension] = [];
        }

        $this->pushCapabilityValue($matrix, 'methods', $rfq->method);
        $this->pushCapabilityValue($matrix, 'materials', $rfq->material);
        $this->pushCapabilityValue($matrix, 'finishes', $rfq->finish);
        $this->pushCapabilityValue($matrix, 'tolerances', $rfq->tolerance);

        $items = $rfq->relationLoaded('items') ? $rfq->items : collect();

        $items->each(function ($item) use (&$matrix): void {
            $this->pushCapabilityValue($matrix, 'methods', $item->method ?? null);
            $this->pushCapabilityValue($matrix, 'materials', $item->material ?? null);
            $this->pushCapabilityValue($matrix, 'finishes', $item->finish ?? null);
            $this->pushCapabilityValue($matrix, 'tolerances', $item->tolerance ?? null);
        });

        return array_map(static fn (array $values) => array_values(array_unique($values)), $matrix);
    }

    private function supplierCapabilityMatrix(Supplier $supplier): array
    {
        $capabilities = is_array($supplier->capabilities) ? $supplier->capabilities : [];

        $matrix = [];

        foreach (self::CAPABILITY_DIMENSIONS as $dimension) {
            $matrix[$dimension] = $this->normalizeCapabilityValues(Arr::wrap($capabilities[$dimension] ?? []));
        }

        return $matrix;
    }

    private function pushCapabilityValue(array &$matrix, string $dimension, mixed $value): void
    {
        $normalized = $this->normalizeValue($value);

        if ($normalized === null) {
            return;
        }

        $matrix[$dimension][] = $normalized;
    }

    /**
     * @param array<int, string> $supplierValues
     */
    private function hasCapabilityMatch(string $required, array $supplierValues): bool
    {
        foreach ($supplierValues as $value) {
            if ($value === '' || $required === '') {
                continue;
            }

            if (Str::contains($value, $required) || Str::contains($required, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, mixed> $values
     * @return array<int, string>
     */
    private function normalizeCapabilityValues(array $values): array
    {
        return collect($values)
            ->map(fn ($value) => $this->normalizeValue($value))
            ->filter()
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values()
            ->all();
    }

    private function geoMatchScore(RFQ $rfq, Supplier $supplier): float
    {
        $delivery = $this->normalizeValue($rfq->delivery_location);
        $buyerCountry = $this->normalizeValue(optional($rfq->company)->country ?? null);
        $supplierCountry = $this->normalizeValue($supplier->country);
        $supplierCity = $this->normalizeValue($supplier->city);

        if ($delivery !== null) {
            if ($supplierCity !== null && Str::contains($delivery, $supplierCity)) {
                return 1.0;
            }

            if ($supplierCountry !== null && Str::contains($delivery, $supplierCountry)) {
                return 1.0;
            }
        }

        if ($buyerCountry !== null && $supplierCountry !== null) {
            if ($buyerCountry === $supplierCountry) {
                return 0.9;
            }

            if (Str::substr($buyerCountry, 0, 2) === Str::substr($supplierCountry, 0, 2)) {
                return 0.7;
            }
        }

        if ($supplierCountry === null) {
            // Missing geo data → neutral baseline until supplier completes profile.
            return 0.5;
        }

        return 0.4;
    }

    private function priceBandScore(Supplier $supplier): float
    {
        $rank = $this->resolvePriceBandRank($supplier);

        return match ($rank) {
            1 => 1.0,
            2 => 0.7,
            3 => 0.4,
            default => 0.2,
        };
    }

    private function resolvePriceBandRank(Supplier $supplier): int
    {
        $capabilities = is_array($supplier->capabilities) ? $supplier->capabilities : [];
        $priceBand = $this->normalizeValue($capabilities['price_band'] ?? null);

        if ($priceBand !== null) {
            return self::PRICE_BAND_RANKS[$priceBand] ?? 4;
        }

        $moq = $supplier->moq;

        if ($moq === null) {
            return 4;
        }

        if ($moq <= 50) {
            return 1;
        }

        if ($moq <= 200) {
            return 2;
        }

        return 3;
    }

    private function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $normalized = Str::of($value)
                ->lower()
                ->trim()
                ->replaceMatches('/\s+/', ' ')
                ->toString();

            return $normalized !== '' ? $normalized : null;
        }

        if (is_scalar($value)) {
            return Str::lower(trim((string) $value)) ?: null;
        }

        return null;
    }
}
