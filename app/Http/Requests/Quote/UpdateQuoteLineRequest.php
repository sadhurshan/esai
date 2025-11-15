<?php

namespace App\Http\Requests\Quote;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Contracts\Validation\Validator;

class UpdateQuoteLineRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'unit_price_minor' => ['nullable', 'integer', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string'],
            'tax_code_ids' => ['nullable', 'array'],
            'tax_code_ids.*' => ['integer', 'min:1'],
            'status' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $keys = ['unit_price', 'unit_price_minor', 'lead_time_days', 'note', 'tax_code_ids', 'status'];
            $payload = $this->all();
            $hasField = collect($keys)->contains(fn (string $key): bool => array_key_exists($key, $payload));

            if (! $hasField) {
                $validator->errors()->add('line', 'Provide at least one field to update.');
            }

            $priceKeys = array_filter([
                'unit_price' => array_key_exists('unit_price', $payload),
                'unit_price_minor' => array_key_exists('unit_price_minor', $payload),
            ]);

            if ($priceKeys !== []
                && $this->input('unit_price') === null
                && $this->input('unit_price_minor') === null) {
                $validator->errors()->add('unit_price', 'Provide either unit_price or unit_price_minor when updating price.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->validated();
    }
}
