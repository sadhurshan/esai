<?php

namespace App\Http\Requests;

class SupplierApplicationStoreRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'capabilities' => ['required', 'array'],
            'capabilities.methods' => ['nullable', 'array'],
            'capabilities.methods.*' => ['string', 'max:160'],
            'capabilities.materials' => ['nullable', 'array'],
            'capabilities.materials.*' => ['string', 'max:160'],
            'capabilities.tolerances' => ['nullable', 'array'],
            'capabilities.tolerances.*' => ['string', 'max:120'],
            'capabilities.finishes' => ['nullable', 'array'],
            'capabilities.finishes.*' => ['string', 'max:160'],
            'capabilities.industries' => ['nullable', 'array'],
            'capabilities.industries.*' => ['string', 'max:160'],
            'address' => ['nullable', 'string', 'max:191'],
            'country' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:160'],
            'moq' => ['nullable', 'integer', 'min:1'],
            'min_order_qty' => ['nullable', 'integer', 'min:1'],
            'lead_time_days' => ['nullable', 'integer', 'min:1'],
            'geo' => ['nullable', 'array'],
            'geo.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'geo.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'certifications' => ['nullable', 'array'],
            'certifications.*' => ['string', 'max:160'],
            'facilities' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'string', 'max:191', 'url'],
            'contact' => ['nullable', 'array'],
            'contact.name' => ['nullable', 'string', 'max:160'],
            'contact.email' => ['nullable', 'email', 'max:191'],
            'contact.phone' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:255'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->validated();
    }
}
