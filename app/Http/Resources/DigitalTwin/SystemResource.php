<?php

namespace App\Http\Resources\DigitalTwin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\System
 */
class SystemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'location_id' => $this->location_id,
            'name' => $this->name,
            'code' => $this->code,
            'notes' => $this->notes,
            'assets_count' => $this->when(isset($this->assets_count), (int) $this->assets_count),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
