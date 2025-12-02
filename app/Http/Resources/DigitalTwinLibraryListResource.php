<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\DigitalTwin */
class DigitalTwinLibraryListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $primaryAsset = null;

        if ($this->relationLoaded('assets')) {
            $primaryAsset = $this->assets->firstWhere('is_primary', true) ?? $this->assets->first();
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'version' => $this->version,
            'tags' => $this->tags ?? [],
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'slug' => $this->category?->slug,
            ]),
            'thumbnail_url' => $this->thumbnailUrl(),
            'primary_asset' => $primaryAsset ? DigitalTwinAssetResource::make($primaryAsset) : null,
            'asset_types' => $this->when($this->relationLoaded('assets'), fn () => $this->assets
                ->map(fn ($asset) => $asset->type?->value ?? $asset->type)
                ->filter()
                ->unique()
                ->values()
                ->all()),
            'published_at' => optional($this->published_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }

    private function thumbnailUrl(): ?string
    {
        if (! $this->thumbnail_path) {
            return null;
        }

        try {
            return Storage::url($this->thumbnail_path);
        } catch (\Throwable) {
            return $this->thumbnail_path;
        }
    }
}
