<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RfqItem */
class RfqItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getRouteKey(),
            'line_no' => $this->line_no,
            'part_name' => $this->part_name,
            'spec' => $this->spec,
            'method' => $this->method,
            'material' => $this->material,
            'tolerance' => $this->tolerance,
            'finish' => $this->finish,
            'quantity' => $this->quantity,
            'uom' => $this->uom,
            'target_price' => $this->target_price,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
