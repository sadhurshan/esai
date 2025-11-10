<?php

namespace App\Http\Resources\DigitalTwin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\MaintenanceProcedure
 */
class MaintenanceProcedureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'code' => $this->code,
            'title' => $this->title,
            'category' => $this->category,
            'estimated_minutes' => (int) $this->estimated_minutes,
            'instructions_md' => $this->instructions_md,
            'tools' => $this->tools_json ?? [],
            'safety' => $this->safety_json ?? [],
            'meta' => $this->meta ?? [],
            'steps' => $this->whenLoaded('steps', fn () => $this->steps->map(fn ($step) => [
                'id' => $step->id,
                'step_no' => $step->step_no,
                'title' => $step->title,
                'instruction_md' => $step->instruction_md,
                'estimated_minutes' => $step->estimated_minutes,
                'attachments' => $step->attachments_json ?? [],
            ])),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
