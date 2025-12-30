<?php

namespace App\Http\Resources;

use App\Models\AiApprovalRequest;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiApprovalRequest */
class AiApprovalRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'workflow_step_id' => $this->workflow_step_id,
            'step_index' => $this->step_index,
            'step_type' => $this->step_type,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'approver_role' => $this->approver_role,
            'status' => $this->status,
            'message' => $this->message,
            'approver_user' => $this->approverUser ? [
                'id' => $this->approverUser->id,
                'name' => $this->approverUser->name,
            ] : null,
            'requested_by' => $this->requestedByUser ? [
                'id' => $this->requestedByUser->id,
                'name' => $this->requestedByUser->name,
            ] : null,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'resolved_at' => optional($this->resolved_at)->toIso8601String(),
        ];
    }
}
