<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RfqDeadlineExtension */
class RfqDeadlineExtensionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rfq_id' => $this->rfq_id,
            'previous_due_at' => $this->previous_due_at?->toIso8601String(),
            'new_due_at' => $this->new_due_at?->toIso8601String(),
            'reason' => $this->reason,
            'extended_by' => $this->extended_by,
            'extended_by_user' => $this->extendedBy ? [
                'id' => $this->extendedBy->id,
                'name' => $this->extendedBy->name,
                'role' => $this->extendedBy->role,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
