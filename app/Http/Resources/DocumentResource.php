<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Document */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'documentable_type' => $this->documentable_type,
            'documentable_id' => $this->documentable_id,
            'kind' => $this->kind,
            'category' => $this->category,
            'visibility' => $this->visibility,
            'version' => $this->version_number,
            'filename' => $this->filename,
            'mime' => $this->mime,
            'size_bytes' => (int) $this->size_bytes,
            'hash' => $this->hash,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'meta' => $this->meta ?? [],
            'watermark' => $this->watermark ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'is_expired' => $this->isExpired(),
            'is_public' => $this->isPublic(),
        ];
    }
}
