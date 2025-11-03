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
        return [
            'id' => $this->id,
            'status' => $this->status,
            'supplier' => [
                'id' => $this->supplier?->id,
                'name' => $this->supplier?->name,
                'rating_avg' => $this->supplier?->rating_avg,
            ],
            'invited_by' => $this->invited_by,
            'invited_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
