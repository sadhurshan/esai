<?php

namespace App\Http\Resources;

use App\Models\Delegation;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Delegation */
class DelegationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'company_id' => (int) $this->company_id,
            'approver_user_id' => (int) $this->approver_user_id,
            'approver_name' => $this->approver?->name,
            'delegate_user_id' => (int) $this->delegate_user_id,
            'delegate_name' => $this->delegate?->name,
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'created_by' => (int) $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
