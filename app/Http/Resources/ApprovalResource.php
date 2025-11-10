<?php

namespace App\Http\Resources;

use App\Enums\ApprovalStatus;
use App\Models\Approval;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Approval */
class ApprovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $status = $this->status instanceof ApprovalStatus ? $this->status->value : $this->status;

        return [
            'id' => $this->id,
            'company_id' => (int) $this->company_id,
            'target_type' => $this->target_type,
            'target_id' => (int) $this->target_id,
            'level_no' => (int) $this->level_no,
            'status' => $status,
            'comment' => $this->comment,
            'approved_by_id' => $this->approved_by_id !== null ? (int) $this->approved_by_id : null,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'expected_approver_user_id' => $this->expectedApproverUserId(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
