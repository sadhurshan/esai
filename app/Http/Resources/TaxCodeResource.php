<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TaxCode */
class TaxCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'rate_percent' => $this->rate_percent !== null ? (float) $this->rate_percent : null,
            'is_compound' => (bool) $this->is_compound,
            'active' => (bool) $this->active,
            'meta' => $this->meta ?? [],
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
