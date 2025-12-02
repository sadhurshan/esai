<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\DigitalTwin */
class DigitalTwinLibraryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'version' => $this->version,
            'revision_notes' => $this->revision_notes,
            'tags' => $this->tags ?? [],
            'thumbnail_url' => $this->thumbnailUrl(),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'slug' => $this->category?->slug,
            ]),
            'specs' => DigitalTwinSpecResource::collection($this->whenLoaded('specs')),
            'assets' => DigitalTwinAssetResource::collection($this->whenLoaded('assets')),
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
