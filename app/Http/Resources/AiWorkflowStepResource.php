<?php

namespace App\Http\Resources;

use App\Models\AiWorkflowStep;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiWorkflowStep
 */
class AiWorkflowStepResource extends JsonResource
{
    public function toArray($request): array
    {
        $workflow = $this->workflow;

        return [
            'workflow_id' => $this->workflow_id,
            'step_index' => $this->step_index,
            'name' => $workflow?->stepName($this->step_index),
            'action_type' => $this->action_type,
            'inputs' => $this->input_json ?? [],
            'draft' => $this->draft_json ?? [],
            'output' => $this->output_json ?? [],
            'approval_status' => $this->approval_status,
            'approved_by' => $this->approved_by,
            'approved_at' => optional($this->approved_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
