<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PurchaseOrderLine */
class PurchaseOrderLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'line_no' => $this->line_no,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'uom' => $this->uom,
            'unit_price' => (float) $this->unit_price,
            'delivery_date' => optional($this->delivery_date)?->toDateString(),
        ];
    }
}
