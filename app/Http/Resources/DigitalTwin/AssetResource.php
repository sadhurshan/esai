<?php

namespace App\Http\Resources\DigitalTwin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Asset
 */
class AssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'system_id' => $this->system_id,
            'location_id' => $this->location_id,
            'name' => $this->name,
            'tag' => $this->tag,
            'serial_no' => $this->serial_no,
            'model_no' => $this->model_no,
            'manufacturer' => $this->manufacturer,
            'commissioned_at' => optional($this->commissioned_at)?->toDateString(),
            'status' => $this->status,
            'meta' => $this->meta ?? [],
            'location' => $this->whenLoaded('location', fn () => new LocationResource($this->location)),
            'system' => $this->whenLoaded('system', fn () => new SystemResource($this->system)),
            'documents' => $this->whenLoaded('documents', fn () => $this->documents->map(static fn ($document) => [
                'id' => $document->id,
                'name' => $document->name,
                'category' => $document->category,
                'kind' => $document->meta['kind'] ?? null,
                'visibility' => $document->meta['visibility'] ?? null,
                'download_url' => $document->download_url ?? null,
            ])),
            'maintenance' => $this->whenLoaded('procedureLinks', function () use ($request) {
                return $this->procedureLinks
                    ->load('procedure')
                    ->map(fn ($link) => (new AssetProcedureLinkResource($link))->toArray($request));
            }),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
