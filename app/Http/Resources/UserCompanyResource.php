<?php

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Company
 */
class UserCompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status;

        if ($status instanceof BackedEnum) {
            $status = $status->value;
        }

        $supplierStatus = $this->supplier_status;

        if ($supplierStatus instanceof BackedEnum) {
            $supplierStatus = $supplierStatus->value;
        }

        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $status,
            'supplier_status' => $supplierStatus,
            'role' => $this->pivot?->role,
            'is_default' => (bool) ($this->pivot?->is_default ?? false),
            'is_active' => $user ? (int) $user->company_id === (int) $this->id : false,
        ];
    }
}
