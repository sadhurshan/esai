<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DigitalTwin */
class DigitalTwinResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'code' => $this->code,
            'title' => $this->title,
            'summary' => $this->summary,
            'status' => $this->status?->value,
            'version' => $this->version,
            'revision_notes' => $this->revision_notes,
            'visibility' => $this->visibility?->value,
            'tags' => $this->tags ?? [],
            'thumbnail_path' => $this->thumbnail_path,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ]),
            'specs' => $this->whenLoaded('specs', fn ($specs) => DigitalTwinSpecResource::collection($specs), []),
            'assets' => $this->whenLoaded('assets', fn ($assets) => DigitalTwinAssetResource::collection($assets), []),
            'published_at' => optional($this->published_at)?->toIso8601String(),
            'archived_at' => optional($this->archived_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
