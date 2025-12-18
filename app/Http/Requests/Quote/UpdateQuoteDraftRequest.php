<?php

namespace App\Http\Requests\Quote;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateQuoteDraftRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'currency' => ['nullable', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'incoterm' => ['nullable', 'string', 'max:8'],
            'payment_terms' => ['nullable', 'string', 'max:120'],
            'min_order_qty' => ['nullable', 'integer', 'min:1'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['integer', 'exists:documents,id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();

        $payload = [];

        foreach (['currency', 'incoterm', 'payment_terms', 'min_order_qty', 'lead_time_days', 'note'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        return $payload;
    }
}
