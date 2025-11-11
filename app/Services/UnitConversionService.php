<?php

namespace App\Services;

use App\Exceptions\UnitConversionException;
use App\Models\Part;
use App\Models\Uom;
use App\Models\UomConversion;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use RuntimeException;
use SplQueue;

class UnitConversionService
{
    private const WORKING_SCALE = 12;

    /**
     * @var array<string, array<int, array<int, array{to:int,factor:BigDecimal,offset:BigDecimal}>>> $graphCache
     */
    private array $graphCache = [];

    /**
     * @var array<string, int>
     */
    private const DIMENSION_SCALE = [
        'mass' => 6,
        'length' => 6,
        'volume' => 6,
        'area' => 6,
        'count' => 3,
        'time' => 6,
        'temperature' => 6,
        'other' => 6,
    ];

    /**
     * @param float|BigDecimal $quantity
     */
    public function convert(float|BigDecimal $quantity, Uom $from, Uom $to): BigDecimal
    {
        if ($from->id === $to->id) {
            return $this->roundForDimension($this->asDecimal($quantity), $to->dimension);
        }

        if ($from->dimension !== $to->dimension) {
            throw new UnitConversionException('Unit conversion requires matching dimensions.');
        }

        $graph = $this->graphForDimension($from->dimension);

        $path = $this->findPath($graph, (int) $from->id, (int) $to->id);

        if ($path === null) {
            throw new UnitConversionException(sprintf('No conversion path from %s to %s.', $from->code, $to->code));
        }

        $result = $this->asDecimal($quantity);

        foreach ($path as $edge) {
            $result = $result
                ->multipliedBy($edge['factor'])
                ->toScale(self::WORKING_SCALE, RoundingMode::HALF_UP);

            if (! $edge['offset']->isZero()) {
                $result = $result
                    ->plus($edge['offset'])
                    ->toScale(self::WORKING_SCALE, RoundingMode::HALF_UP);
            }
        }

        return $this->roundForDimension($result, $to->dimension);
    }

    /**
     * @param float|BigDecimal $quantity
     */
    public function toPartBase(Part $part, float|BigDecimal $quantity, Uom $from): BigDecimal
    {
        $base = $this->resolvePartBaseUom($part);

        return $this->convert($quantity, $from, $base);
    }

    public function resolvePartBaseUom(Part $part): Uom
    {
        if ($part->relationLoaded('baseUom') && $part->baseUom instanceof Uom) {
            return $part->baseUom;
        }

        if ($part->base_uom_id !== null) {
            $base = Uom::query()->find($part->base_uom_id);

            if ($base instanceof Uom) {
                $part->setRelation('baseUom', $base);

                return $base;
            }
        }

        if ($part->uom !== null) {
            $base = Uom::query()->where('code', $part->uom)->first();

            if ($base instanceof Uom) {
                $part->setRelation('baseUom', $base);

                return $base;
            }
        }

        throw new UnitConversionException('Part base UoM not defined.');
    }

    /**
     * @param array<int, array<int, array{to:int,factor:BigDecimal,offset:BigDecimal}>> $graph
     * @return array<int, array{to:int,factor:BigDecimal,offset:BigDecimal}>|null
     */
    private function findPath(array $graph, int $fromId, int $toId): ?array
    {
        if ($fromId === $toId) {
            return [];
        }

        $queue = new SplQueue();
        $queue->enqueue([$fromId, []]);

        $visited = [$fromId];

        while (! $queue->isEmpty()) {
            [$node, $path] = $queue->dequeue();

            foreach ($graph[$node] ?? [] as $edge) {
                if ($edge['to'] === $toId) {
                    return array_merge($path, [$edge]);
                }

                if (in_array($edge['to'], $visited, true)) {
                    continue;
                }

                $visited[] = $edge['to'];
                $queue->enqueue([$edge['to'], array_merge($path, [$edge])]);
            }
        }

        return null;
    }

    /**
     * @return array<int, array<int, array{to:int,factor:BigDecimal,offset:BigDecimal}>>
     */
    private function graphForDimension(string $dimension): array
    {
        if (isset($this->graphCache[$dimension])) {
            return $this->graphCache[$dimension];
        }

        $conversions = UomConversion::query()
            ->whereNull('deleted_at')
            ->whereHas('from', fn ($query) => $query->where('dimension', $dimension))
            ->whereHas('to', fn ($query) => $query->where('dimension', $dimension))
            ->with(['from:id,dimension', 'to:id,dimension'])
            ->get();

        $graph = [];

        foreach ($conversions as $conversion) {
            $factor = BigDecimal::of((string) $conversion->factor);
            $offset = BigDecimal::of((string) $conversion->offset);

            $graph[(int) $conversion->from_uom_id][] = [
                'to' => (int) $conversion->to_uom_id,
                'factor' => $factor,
                'offset' => $offset,
            ];

            $graph[(int) $conversion->to_uom_id][] = [
                'to' => (int) $conversion->from_uom_id,
                'factor' => $this->inverseFactor($factor),
                'offset' => $this->inverseOffset($factor, $offset),
            ];
        }

        return $this->graphCache[$dimension] = $graph;
    }

    private function inverseFactor(BigDecimal $factor): BigDecimal
    {
        if ($factor->isZero()) {
            throw new RuntimeException('Conversion factor cannot be zero.');
        }

        return BigDecimal::one()->dividedBy($factor, self::WORKING_SCALE, RoundingMode::HALF_UP);
    }

    private function inverseOffset(BigDecimal $factor, BigDecimal $offset): BigDecimal
    {
        if ($offset->isZero()) {
            return BigDecimal::zero();
        }

        return $offset
            ->negated()
            ->dividedBy($factor, self::WORKING_SCALE, RoundingMode::HALF_UP);
    }

    /**
     * @param float|BigDecimal $value
     */
    private function asDecimal(float|BigDecimal $value): BigDecimal
    {
        if ($value instanceof BigDecimal) {
            return $value->toScale(self::WORKING_SCALE, RoundingMode::HALF_UP);
        }

        return BigDecimal::of((string) $value)->toScale(self::WORKING_SCALE, RoundingMode::HALF_UP);
    }

    private function roundForDimension(BigDecimal $value, string $dimension): BigDecimal
    {
        $scale = self::DIMENSION_SCALE[$dimension] ?? 6;

    return $value->toScale($scale, RoundingMode::DOWN);
    }

    public function resetCache(): void
    {
        $this->graphCache = [];
    }
}
