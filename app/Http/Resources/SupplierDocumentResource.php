<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SupplierDocument */
class SupplierDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'company_id' => $this->company_id,
            'type' => $this->type,
            'status' => $this->status,
            'path' => $this->path,
            // TODO: confirm whether supplier document responses should expose signed download URLs per documents spec.
            'mime' => $this->mime,
            'size_bytes' => (int) $this->size_bytes,
            'issued_at' => $this->issued_at?->toDateString(),
            'expires_at' => $this->expires_at?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
