<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ModelTrainingJob */
class ModelTrainingJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'company_id' => $this->company_id,
            'feature' => $this->feature,
            'status' => $this->status,
            'microservice_job_id' => $this->microservice_job_id,
            'parameters' => $this->parameters_json ?? [],
            'result' => $this->result_json,
            'error_message' => $this->error_message,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
