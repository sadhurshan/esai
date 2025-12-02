<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Throwable;

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
            'document_id' => $this->document_id,
            'type' => $this->type,
            'status' => $this->status,
            'path' => $this->path,
            'download_url' => $this->buildDownloadUrl(),
            'mime' => $this->mime,
            'filename' => $this->document?->filename ?? $this->fallbackFilename(),
            'size_bytes' => (int) $this->size_bytes,
            'issued_at' => $this->issued_at?->toDateString(),
            'expires_at' => $this->expires_at?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function fallbackFilename(): ?string
    {
        if (! is_string($this->path) || $this->path === '') {
            return null;
        }

        $name = basename($this->path);

        return $name === '' ? null : $name;
    }

    private function buildDownloadUrl(): ?string
    {
        if (blank($this->path)) {
            return null;
        }

        $disk = (string) config('documents.disk', config('filesystems.default', 'local'));
        $storage = Storage::disk($disk);

        if (! $storage->exists($this->path)) {
            return null;
        }

        $expiresAt = now()->addMinutes(max(1, (int) config('documents.download_ttl_minutes', 10)));

        try {
            return $storage->temporaryUrl($this->path, $expiresAt);
        } catch (Throwable) {
            try {
                return $storage->url($this->path);
            } catch (Throwable) {
                return null;
            }
        }
    }
}
