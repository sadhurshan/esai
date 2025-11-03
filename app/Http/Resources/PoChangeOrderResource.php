<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PoChangeOrder */
class PoChangeOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'reason' => $this->reason,
            'status' => $this->status,
            'changes_json' => $this->changes_json,
            'po_revision_no' => $this->po_revision_no,
            'proposed_by' => $this->whenLoaded('proposedByUser', function (): array {
                return [
                    'id' => $this->proposedByUser?->id,
                    'name' => $this->proposedByUser?->name,
                    'email' => $this->proposedByUser?->email,
                ];
            }),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
