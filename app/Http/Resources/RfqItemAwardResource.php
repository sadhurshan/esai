<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RfqItemAward */
class RfqItemAwardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'rfq_id' => $this->rfq_id,
            'rfq_item_id' => $this->rfq_item_id,
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier?->getKey(),
                'name' => $this->supplier?->name,
            ]),
            'quote_id' => $this->quote_id,
            'quote_item_id' => $this->quote_item_id,
            'awarded_qty' => $this->awarded_qty,
            'po_id' => $this->po_id,
            'status' => $this->status?->value ?? $this->status,
            'awarded_at' => optional($this->awarded_at)->toIso8601String(),
        ];
    }
}
