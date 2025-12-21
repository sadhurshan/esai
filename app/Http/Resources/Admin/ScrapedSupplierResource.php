<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ScrapedSupplier */
class ScrapedSupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'scrape_job_id' => $this->scrape_job_id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'website' => $this->website,
            'description' => $this->description,
            'industry_tags' => $this->industry_tags ?? [],
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'phone' => $this->phone,
            'email' => $this->email,
            'contact_person' => $this->contact_person,
            'certifications' => $this->certifications ?? [],
            'product_summary' => $this->product_summary,
            'source_url' => $this->source_url,
            'confidence' => $this->confidence !== null ? (float) $this->confidence : null,
            'metadata' => $this->metadata_json ?? [],
            'status' => $this->status?->value ?? null,
            'approved_supplier_id' => $this->approved_supplier_id,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'review_notes' => $this->review_notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
