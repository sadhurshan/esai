<?php

namespace App\Http\Resources\Inventory;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryLocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = $this->resource;
        $type = $this->getAttribute('location_type');

        if ($type === null) {
            $type = $resource instanceof Warehouse ? 'site' : 'bin';
        }
        $onHand = (float) ($this->getAttribute('aggregate_on_hand') ?? 0);
        $allocated = (float) ($this->getAttribute('aggregate_allocated') ?? 0);

        return [
            'id' => (string) $this->getKey(),
            'name' => $this->name,
            'site_name' => $type === 'site'
                ? $this->name
                : ($this->relationLoaded('warehouse') ? $this->warehouse?->name : null),
            'type' => $type,
            'code' => $this->code,
            'is_default_receiving' => false,
            'supports_negative' => false,
            'on_hand' => $onHand,
            'available' => $onHand - $allocated,
        ];
    }
}
