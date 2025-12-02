<?php

namespace App\Http\Resources;

use App\Models\CompanyInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CompanyInvitation */
class CompanyInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->resolveStatus(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'invited_by' => $this->invited_by_user_id,
            'company_id' => $this->company_id,
            'pending_user_id' => $this->pending_user_id,
            'message' => $this->message,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function resolveStatus(): string
    {
        if ($this->isAccepted()) {
            return 'accepted';
        }

        if ($this->isRevoked()) {
            return 'revoked';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        return 'pending';
    }
}
