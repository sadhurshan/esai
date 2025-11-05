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
            'capabilities' => ['required', 'array', 'min:1'],
            'capabilities.*' => ['string', 'max:160'],
            'materials' => ['nullable', 'array'],
            'materials.*' => ['string', 'max:160'],
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
