<?php

namespace App\Http\Resources;

use App\Models\SupplierEsgRecord;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin SupplierEsgRecord */
class SupplierEsgRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $document = $this->document;
        $downloadUrl = null;

        if ($document !== null) {
            $disk = config('documents.disk', config('filesystems.default', 'local'));

            try {
                $downloadUrl = Storage::disk($disk)->temporaryUrl($document->path, now()->addMinutes(10));
            } catch (\Throwable) {
                $downloadUrl = Storage::disk($disk)->url($document->path);
            }
        }

        return [
            'id' => $this->id,
            'supplier_id' => (int) $this->supplier_id,
            'category' => $this->category?->value,
            'name' => $this->name,
            'description' => $this->description,
            'meta' => $this->data_json ?? [],
            'approved_at' => $this->approved_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'download_url' => $downloadUrl,
            'document_id' => $document?->id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'is_expired' => $this->isExpired(),
        ];
    }
}
