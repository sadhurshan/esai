<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DigitalTwin */
class DigitalTwinListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'status' => $this->status?->value,
            'version' => $this->version,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ]),
            'tags' => $this->tags ?? [],
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'published_at' => optional($this->published_at)?->toIso8601String(),
        ];
    }
}
