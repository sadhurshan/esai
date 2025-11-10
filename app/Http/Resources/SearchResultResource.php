<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array<string, mixed> $resource
 */
class SearchResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'type' => $this['type'] ?? null,
            'id' => $this['id'] ?? null,
            'title' => $this['title'] ?? null,
            'identifier' => $this['identifier'] ?? null,
            'status' => $this['status'] ?? null,
            'created_at' => $this['created_at'] ?? null,
            'snippet' => $this['snippet'] ?? null,
            'additional' => $this['additional'] ?? [],
        ];
    }
}
