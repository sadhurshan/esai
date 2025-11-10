<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class AnalyticsOverviewResource extends JsonResource
{
    /**
     * @param Collection<string, \Illuminate\Database\Eloquent\Collection> $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function toArray($request): array
    {
        return collect($this->resource)
            ->map(fn ($snapshots) => AnalyticsSnapshotResource::collection($snapshots)->toArray($request))
            ->toArray();
    }
}
