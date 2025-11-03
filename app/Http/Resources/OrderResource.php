<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'party_type' => $this->party_type,
            'party_name' => $this->party_name,
            'item_name' => $this->item_name,
            'quantity' => $this->quantity,
            'total_usd' => $this->total_usd,
            'ordered_at' => optional($this->ordered_at)?->toIso8601String(),
            'status' => $this->status,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
