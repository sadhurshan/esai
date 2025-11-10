<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaxCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return in_array($user->role, ['owner', 'buyer_admin'], true) && $user->company_id !== null;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('tax_codes', 'code')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                ),
            ],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['vat', 'gst', 'sales', 'withholding', 'custom'])],
            'rate_percent' => ['nullable', 'numeric', 'gte:0', 'lte:999.999'],
            'is_compound' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'meta' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->normalizeCode($this->input('code')),
            'rate_percent' => $this->normalizeRate($this->input('rate_percent')),
        ]);
    }

    public function payload(): array
    {
        return [
            'code' => $this->normalizeCode($this->input('code')),
            'name' => $this->input('name'),
            'type' => $this->input('type'),
            'rate_percent' => $this->input('rate_percent') !== null ? (float) $this->input('rate_percent') : null,
            'is_compound' => (bool) $this->input('is_compound', false),
            'active' => (bool) $this->input('active', true),
            'meta' => $this->input('meta') ?? [],
        ];
    }

    private function normalizeCode(?string $code): ?string
    {
        return $code === null ? null : strtoupper($code);
    }

    private function normalizeRate($rate): ?string
    {
        if ($rate === null || $rate === '') {
            return null;
        }

        return number_format((float) $rate, 3, '.', '');
    }
}
