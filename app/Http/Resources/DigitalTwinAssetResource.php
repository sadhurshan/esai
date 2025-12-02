<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\DigitalTwinAsset */
class DigitalTwinAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value ?? $this->type,
            'filename' => $this->filename,
            'path' => $this->path,
            'mime' => $this->mime,
            'size_bytes' => $this->size_bytes,
            'is_primary' => (bool) $this->is_primary,
            'checksum' => $this->checksum,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'download_url' => $this->downloadUrl(),
        ];
    }

    private function downloadUrl(): ?string
    {
        $diskName = $this->disk ?? config('digital-twins.assets.disk');
        $disk = Storage::disk($diskName);

        if (! $this->path || ! method_exists($disk, 'temporaryUrl')) {
            return null;
        }

        try {
            return $disk->temporaryUrl($this->path, now()->addMinutes(15));
        } catch (\Throwable) {
            return null;
        }
    }
}
