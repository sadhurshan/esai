<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Document */
class RfqAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'document_id' => (string) $this->id,
            'filename' => $this->filename,
            'mime' => $this->mime,
            'size_bytes' => $this->size_bytes !== null ? (int) $this->size_bytes : null,
            'url' => $this->temporaryDownloadUrl(),
            'uploaded_at' => $this->created_at?->toIso8601String(),
            'uploaded_by' => $this->uploadedBySummary(),
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function uploadedBySummary(): ?array
    {
        $meta = $this->meta ?? [];

        $uploaderId = $meta['uploaded_by'] ?? null;
        $uploaderName = $meta['uploaded_by_name'] ?? null;

        if ($uploaderId === null && $uploaderName === null) {
            return null;
        }

        return [
            'id' => $uploaderId !== null ? (string) $uploaderId : null,
            'name' => $uploaderName,
        ];
    }
}
