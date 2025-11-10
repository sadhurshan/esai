<?php

namespace App\Http\Resources\DigitalTwin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Location
 */
class LocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'code' => $this->code,
            'parent_id' => $this->parent_id,
            'notes' => $this->notes,
            'systems_count' => $this->when(isset($this->systems_count), (int) $this->systems_count),
            'assets_count' => $this->when(isset($this->assets_count), (int) $this->assets_count),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
