<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RfqInvitation */
class RfqInvitationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $supplier = $this->supplier;
        $capabilities = is_array($supplier?->capabilities) ? $supplier->capabilities : null;

        return [
            'id' => (string) $this->id,
            'rfq_id' => (string) $this->rfq_id,
            'supplier_id' => (string) $this->supplier_id,
            'status' => $this->status,
            'invited_by' => $this->invited_by !== null ? (string) $this->invited_by : null,
            'invited_at' => optional($this->created_at)?->toIso8601String(),
            'responded_at' => optional($this->responded_at)?->toIso8601String(),
            'supplier' => $supplier ? [
                'id' => (string) $supplier->id,
                'name' => $supplier->name,
                'city' => $supplier->city,
                'country' => $supplier->country,
                'rating_avg' => $supplier->rating_avg,
                'capabilities' => $capabilities,
            ] : null,
        ];
    }
}
