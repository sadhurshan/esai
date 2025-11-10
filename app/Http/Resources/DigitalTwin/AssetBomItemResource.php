<?php

namespace App\Http\Resources\DigitalTwin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AssetBomItem
 */
class AssetBomItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->asset_id,
            'part_id' => $this->part_id,
            'quantity' => (float) $this->quantity,
            'uom' => $this->uom,
            'criticality' => $this->criticality,
            'notes' => $this->notes,
            'part' => $this->whenLoaded('part', fn () => [
                'id' => $this->part->id,
                'part_number' => $this->part->part_number,
                'name' => $this->part->name,
                'uom' => $this->part->uom,
            ]),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
