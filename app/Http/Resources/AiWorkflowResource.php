<?php

namespace App\Http\Resources;

use App\Models\AiWorkflow;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiWorkflow
 */
class AiWorkflowResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'workflow_id' => $this->workflow_id,
            'workflow_type' => $this->workflow_type,
            'status' => $this->status,
            'current_step' => $this->current_step,
            'last_event_type' => $this->last_event_type,
            'last_event_time' => optional($this->last_event_time)->toIso8601String(),
            'steps' => $this->stepSummaries(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }

    private function stepSummaries(): array
    {
        $steps = $this->steps_json;

        if (! is_array($steps)) {
            return [];
        }

        $items = $steps['steps'] ?? $steps;

        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(function ($step) {
                return [
                    'step_index' => isset($step['step_index']) ? (int) $step['step_index'] : null,
                    'action_type' => $step['action_type'] ?? null,
                    'approval_status' => $step['approval_status'] ?? null,
                    'name' => $step['name'] ?? null,
                    'approval_requirements' => $step['approval_requirements'] ?? null,
                    'has_pending_approval_request' => (bool) ($step['has_pending_approval_request'] ?? false),
                ];
            })
            ->all();
    }
}
