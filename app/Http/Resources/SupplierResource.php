<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Supplier */
class SupplierResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $company = $this->relationLoaded('company') ? $this->company : null;
        $profile = $company && $company->relationLoaded('profile') ? $company->profile : null;

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'capabilities' => $this->capabilities,
            'status' => $this->status,
            'rating_avg' => $this->rating_avg !== null ? (float) $this->rating_avg : null,
            'risk_grade' => $this->risk_grade?->value,
            'contact' => [
                'email' => $this->email,
                'phone' => $this->phone,
                'website' => $this->website,
            ],
            'address' => [
                'line1' => $this->address,
                'city' => $this->city,
                'country' => $this->country,
            ],
            'geo' => [
                'lat' => $this->geo_lat,
                'lng' => $this->geo_lng,
            ],
            'lead_time_days' => $this->lead_time_days,
            'moq' => $this->moq,
            'branding' => [
                'logo_url' => $profile->logo_url ?? null,
                'mark_url' => $profile->mark_url ?? null,
            ],
            'company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'website' => $company->website,
                'country' => $company->country,
                'supplier_status' => $company->supplier_status?->value,
                'is_verified' => $company->is_verified,
            ] : null,
            'certificates' => [
                'valid' => isset($this->valid_certificates_count) ? (int) $this->valid_certificates_count : 0,
                'expiring' => isset($this->expiring_certificates_count) ? (int) $this->expiring_certificates_count : 0,
                'expired' => isset($this->expired_certificates_count) ? (int) $this->expired_certificates_count : 0,
            ],
            'documents' => $this->formatDocuments($request),
            'verified_at' => optional($this->verified_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }

    private function formatDocuments(Request $request): array
    {
        if (! $this->relationLoaded('documents')) {
            return [];
        }

        $documents = $this->documents;

        if (method_exists($documents, 'load')) {
            $documents->load('document');
        }

        return SupplierDocumentResource::collection($documents)->toArray($request);
    }
}
