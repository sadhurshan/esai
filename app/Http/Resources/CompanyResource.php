<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Company */
class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'supplier_status' => $this->supplier_status instanceof \BackedEnum ? $this->supplier_status->value : $this->supplier_status,
            'directory_visibility' => $this->directory_visibility,
            'supplier_profile_completed_at' => $this->supplier_profile_completed_at?->toIso8601String(),
            'is_verified' => (bool) $this->is_verified,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verified_by' => $this->verified_by,
            'registration_no' => $this->registration_no,
            'tax_id' => $this->tax_id,
            'country' => $this->country,
            'email_domain' => $this->email_domain,
            'primary_contact_name' => $this->primary_contact_name,
            'primary_contact_email' => $this->primary_contact_email,
            'primary_contact_phone' => $this->primary_contact_phone,
            'address' => $this->address,
            'phone' => $this->phone,
            'website' => $this->website,
            'region' => $this->region,
            'rejection_reason' => $this->rejection_reason,
            'owner_user_id' => $this->owner_user_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
