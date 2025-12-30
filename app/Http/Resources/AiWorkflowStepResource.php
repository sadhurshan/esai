<?php

namespace App\Http\Resources;

use App\Models\AiApprovalRequest;
use App\Models\AiWorkflowStep;
use App\Services\Ai\WorkflowService;
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
            'approval_requirements' => $this->resolveApprovalRequirements(),
            'approval_request' => $this->resolvePendingApprovalRequest($request),
        ];
    }

    private function resolveApprovalRequirements(): array
    {
        $workflow = $this->workflow;
        $metadata = $workflow?->stepMetadata($this->step_index);
        $requirements = $metadata['approval_requirements'] ?? null;

        if (is_array($requirements)) {
            return $requirements;
        }

        return app(WorkflowService::class)->resolveRequiredApprovals($this->resource);
    }

    private function resolvePendingApprovalRequest($request): ?array
    {
        $pending = $this->relationLoaded('pendingApprovalRequest')
            ? $this->pendingApprovalRequest
            : null;

        if ($pending === null && $this->relationLoaded('approvalRequests')) {
            $pending = $this->approvalRequests->first(static fn (AiApprovalRequest $requestModel): bool => $requestModel->isPending());
        }

        if ($pending === null) {
            $pending = $this->approvalRequests()
                ->where('status', AiApprovalRequest::STATUS_PENDING)
                ->latest()
                ->first();
        }

        if (! $pending instanceof AiApprovalRequest) {
            return null;
        }

        return (new AiApprovalRequestResource($pending))->toArray($request);
    }
}
