<?php

namespace App\Services\DigitalTwin;

use App\Models\Asset;
use App\Models\AssetBomItem;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BomService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @param array<int, array{part_id:int|numeric, quantity:mixed, uom:string, criticality:string, notes?:string|null}> $items
     */
    public function syncBom(Asset $asset, array $items): Collection
    {
        $normalized = $this->normalizeItems($items);

        return $this->database->transaction(function () use ($asset, $normalized): Collection {
            /** @var Collection<int, AssetBomItem> $existing */
            $existing = $asset->bomItems()->get()->keyBy('part_id');
            $seenPartIds = [];

            foreach ($normalized as $payload) {
                $partId = (int) $payload['part_id'];

                if (in_array($partId, $seenPartIds, true)) {
                    throw ValidationException::withMessages([
                        'items' => ['Duplicate part detected in payload.'],
                    ]);
                }

                $seenPartIds[] = $partId;
                $before = null;

                /** @var AssetBomItem|null $item */
                $item = $existing->get($partId);

                if ($item instanceof AssetBomItem) {
                    $changes = array_diff_assoc(
                        Arr::only($payload, ['quantity', 'uom', 'criticality', 'notes']),
                        Arr::only($item->getAttributes(), ['quantity', 'uom', 'criticality', 'notes'])
                    );

                    if ($changes !== []) {
                        $before = Arr::only($item->getAttributes(), ['quantity', 'uom', 'criticality', 'notes']);
                        $item->fill($payload);
                        $item->save();
                        $this->auditLogger->updated($item, $before, Arr::only($item->getAttributes(), array_keys($changes)));
                    }

                    continue;
                }

                $created = $asset->bomItems()->create($payload);
                $this->auditLogger->created($created, Arr::only($created->getAttributes(), ['part_id', 'quantity', 'uom', 'criticality']));
            }

            $normalizedIds = array_column($normalized, 'part_id');

            foreach ($existing as $partId => $item) {
                if (! in_array($partId, $normalizedIds, true)) {
                    $before = $item->getAttributes();
                    $item->delete();
                    $this->auditLogger->deleted($item, $before);
                }
            }

            return $asset->bomItems()->with('part')->get();
        });
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return list<array{asset_id:int, part_id:int, quantity:float, uom:string, criticality:string, notes:string|null}>
     */
    private function normalizeItems(array $items): array
    {
        $allowedCriticality = ['low', 'medium', 'high'];
        $normalized = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                throw ValidationException::withMessages([
                    "items.$index" => ['Invalid BOM payload.'],
                ]);
            }

            $partId = (int) ($item['part_id'] ?? 0);
            $quantity = $item['quantity'] ?? null;
            $uom = (string) ($item['uom'] ?? '');
            $criticality = strtolower((string) ($item['criticality'] ?? 'medium'));
            $notes = $item['notes'] ?? null;

            if ($partId <= 0) {
                throw ValidationException::withMessages([
                    "items.$index.part_id" => ['Part is required.'],
                ]);
            }

            if (! is_numeric($quantity) || (float) $quantity <= 0) {
                throw ValidationException::withMessages([
                    "items.$index.quantity" => ['Quantity must be greater than zero.'],
                ]);
            }

            if ($uom === '') {
                throw ValidationException::withMessages([
                    "items.$index.uom" => ['Unit of measure is required.'],
                ]);
            }

            if (! in_array($criticality, $allowedCriticality, true)) {
                throw ValidationException::withMessages([
                    "items.$index.criticality" => ['Invalid criticality provided.'],
                ]);
            }

            $normalized[] = [
                'part_id' => $partId,
                'quantity' => round((float) $quantity, 3),
                'uom' => $uom,
                'criticality' => $criticality,
                'notes' => $notes !== null ? (string) $notes : null,
            ];
        }

        return $normalized;
    }
}
