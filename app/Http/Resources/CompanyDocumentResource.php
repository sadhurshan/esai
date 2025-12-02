<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\CompanyDocument */
class CompanyDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Document|null $linked */
        $linked = $this->whenLoaded('document');

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'document_id' => $this->document_id,
            'type' => $this->type,
            'filename' => $linked?->filename ?? $this->fallbackFilename(),
            'mime' => $linked?->mime,
            'size_bytes' => $linked?->size_bytes !== null ? (int) $linked->size_bytes : null,
            'download_url' => $this->buildDownloadUrl($linked),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function fallbackFilename(): ?string
    {
        if (! is_string($this->path) || $this->path === '') {
            return null;
        }

        $basename = basename($this->path);

        return $basename === '' ? null : $basename;
    }

    private function buildDownloadUrl(?Document $document): ?string
    {
        if ($document instanceof Document) {
            return $document->temporaryDownloadUrl((int) config('documents.download_ttl_minutes', 10));
        }

        if (! is_string($this->path) || $this->path === '') {
            return null;
        }

        $disk = (string) config('documents.disk', config('filesystems.default', 'local'));
        $expiresAt = now()->addMinutes(max(1, (int) config('documents.download_ttl_minutes', 10)));

        try {
            $storage = Storage::disk($disk);
        } catch (\Throwable) {
            return null;
        }

        try {
            return $storage->temporaryUrl($this->path, $expiresAt);
        } catch (\Throwable) {
            try {
                return $storage->url($this->path);
            } catch (\Throwable) {
                return null;
            }
        }
    }
}
