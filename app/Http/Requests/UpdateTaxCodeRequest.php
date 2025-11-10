<?php

namespace App\Http\Requests;

use App\Models\TaxCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaxCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $taxCode = $this->route('tax_code');

        if ($user === null || ! $taxCode instanceof TaxCode) {
            return false;
        }

        return in_array($user->role, ['owner', 'buyer_admin'], true)
            && $user->company_id !== null
            && (int) $taxCode->company_id === (int) $user->company_id;
    }

    public function rules(): array
    {
        /** @var TaxCode|null $taxCode */
        $taxCode = $this->route('tax_code');
        $companyId = $this->user()?->company_id;

        return [
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('tax_codes', 'code')
                    ->where(fn ($query) => $query
                        ->where('company_id', $companyId)
                        ->whereNull('deleted_at')
                    )
                    ->ignore($taxCode?->id),
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
        if ($this->has('code')) {
            $this->merge(['code' => $this->normalizeCode($this->input('code'))]);
        }

        if ($this->has('rate_percent')) {
            $this->merge(['rate_percent' => $this->normalizeRate($this->input('rate_percent'))]);
        }
    }

    public function payload(): array
    {
        $payload = [
            'code' => $this->normalizeCode($this->input('code')),
            'name' => $this->input('name'),
            'type' => $this->input('type'),
        ];

        if ($this->has('rate_percent')) {
            $payload['rate_percent'] = $this->input('rate_percent') !== null
                ? (float) $this->input('rate_percent')
                : null;
        }

        if ($this->has('is_compound')) {
            $payload['is_compound'] = (bool) $this->input('is_compound');
        }

        if ($this->has('active')) {
            $payload['active'] = (bool) $this->input('active');
        }

        if ($this->has('meta')) {
            $payload['meta'] = $this->input('meta') ?? [];
        }

        return $payload;
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
