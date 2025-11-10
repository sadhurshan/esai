<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\FxRate */
class FxRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'base_code' => $this->base_code,
            'quote_code' => $this->quote_code,
            'rate' => number_format((float) $this->rate, 8, '.', ''),
            'as_of' => optional($this->as_of)?->toDateString(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
