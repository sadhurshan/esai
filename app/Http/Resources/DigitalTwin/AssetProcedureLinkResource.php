<?php

namespace App\Http\Resources\DigitalTwin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AssetProcedureLink
 */
class AssetProcedureLinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->asset_id,
            'maintenance_procedure_id' => $this->maintenance_procedure_id,
            'frequency_value' => (int) $this->frequency_value,
            'frequency_unit' => $this->frequency_unit,
            'last_done_at' => optional($this->last_done_at)?->toIso8601String(),
            'next_due_at' => optional($this->next_due_at)?->toIso8601String(),
            'meta' => $this->meta ?? [],
            'procedure' => $this->whenLoaded('procedure', fn () => new MaintenanceProcedureResource($this->procedure)),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
