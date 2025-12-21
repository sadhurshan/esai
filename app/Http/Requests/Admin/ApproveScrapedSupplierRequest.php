<?php

namespace App\Http\Requests\Admin;

use App\Models\SupplierDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveScrapedSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'size:2'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.methods' => ['nullable', 'array'],
            'capabilities.methods.*' => ['string', 'max:160'],
            'capabilities.materials' => ['nullable', 'array'],
            'capabilities.materials.*' => ['string', 'max:160'],
            'capabilities.finishes' => ['nullable', 'array'],
            'capabilities.finishes.*' => ['string', 'max:160'],
            'capabilities.industries' => ['nullable', 'array'],
            'capabilities.industries.*' => ['string', 'max:160'],
            'capabilities.tolerances' => ['nullable', 'array'],
            'capabilities.tolerances.*' => ['string', 'max:120'],
            'capabilities.price_band' => ['nullable', 'string', 'max:120'],
            'capabilities.summary' => ['nullable', 'string'],
            'product_summary' => ['nullable', 'string'],
            'certifications' => ['nullable', 'array'],
            'certifications.*' => ['string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lead_time_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'moq' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'attachment' => ['nullable', 'file', 'max:51200'],
            'attachment_type' => ['nullable', 'string', 'required_with:attachment', Rule::in(SupplierDocument::DOCUMENT_TYPES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('country') && is_string($this->country)) {
            $this->merge(['country' => strtoupper($this->country)]);
        }
    }
}
