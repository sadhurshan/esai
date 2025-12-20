<?php

namespace App\Http\Resources;

use App\Models\AiEvent;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiEvent
 */
class AiWorkflowEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $workflow = $this->workflowContext();
        $payload = $this->payloadContext();

        return [
            'event' => $this->feature,
            'status' => $this->status,
            'message' => $this->error_message,
            'latency_ms' => $this->latency_ms,
            'timestamp' => optional($this->created_at)->toIso8601String(),
            'workflow' => $workflow,
            'payload' => $payload,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                    'email' => $this->user?->email,
                ];
            }),
        ];
    }

    private function workflowContext(): array
    {
        $requestPayload = $this->request_json;

        if (! is_array($requestPayload)) {
            return [];
        }

        $workflow = $requestPayload['workflow'] ?? [];

        if (! is_array($workflow)) {
            return [];
        }

        return [
            'workflow_id' => $workflow['workflow_id'] ?? null,
            'workflow_type' => $workflow['workflow_type'] ?? null,
            'status' => $workflow['status'] ?? null,
            'current_step' => $workflow['current_step'] ?? null,
            'step_index' => $workflow['step_index'] ?? null,
            'action_type' => $workflow['action_type'] ?? null,
        ];
    }

    private function payloadContext(): ?array
    {
        $requestPayload = $this->request_json;

        if (! is_array($requestPayload)) {
            return null;
        }

        $payload = $requestPayload['payload'] ?? null;

        return is_array($payload) ? $payload : null;
    }
}
