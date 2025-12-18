<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin \App\Models\InventoryMovement */
class InventoryMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $line = $this->relationLoaded('lines') ? $this->lines->first() : null;

        return [
            'id' => (string) $this->getKey(),
            'movement_number' => $this->movement_number,
            'type' => strtoupper((string) $this->type),
            'moved_at' => $this->moved_at?->toIso8601String(),
            'status' => $this->status,
            'lines_count' => (int) ($this->getAttribute('lines_count') ?? ($this->relationLoaded('lines') ? $this->lines->count() : 0)),
            'from_location_name' => $line ? ($line->fromBin?->name ?? $line->fromWarehouse?->name) : null,
            'to_location_name' => $line ? ($line->toBin?->name ?? $line->toWarehouse?->name) : null,
            'reference_label' => $this->referenceLabel(),
        ];
    }

    private function referenceLabel(): ?string
    {
        $type = $this->reference_type;
        $referenceId = $this->reference_id;

        if ($type === null && $referenceId === null) {
            return null;
        }

        if ($type !== null && $referenceId !== null) {
            return Str::headline($type) . ' #' . $referenceId;
        }

        return $type ?? $referenceId;
    }
}
