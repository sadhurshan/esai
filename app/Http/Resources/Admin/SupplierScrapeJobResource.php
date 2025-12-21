<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SupplierScrapeJob */
class SupplierScrapeJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'company_id' => $this->company_id,
            'user_id' => $this->user_id,
            'query' => $this->query,
            'region' => $this->region,
            'status' => $this->status?->value,
            'result_count' => $this->result_count,
            'error_message' => $this->error_message,
            'parameters' => $this->parameters_json ?? [],
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
