<?php

namespace App\Http\Requests\Quote;

use App\Http\Requests\ApiFormRequest;
use App\Models\Quote;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class StoreQuoteLineRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        /** @var Quote|null $quote */
        $quote = $this->route('quote');
        $rfqId = $quote instanceof Quote ? $quote->rfq_id : null;

        return [
            'rfq_item_id' => [
                'required',
                'integer',
                Rule::exists('rfq_items', 'id')->where(function ($query) use ($rfqId): void {
                    if ($rfqId !== null) {
                        $query->where('rfq_id', $rfqId);
                    }
                }),
            ],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'unit_price_minor' => ['nullable', 'integer', 'min:0'],
            'lead_time_days' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string'],
            'tax_code_ids' => ['nullable', 'array'],
            'tax_code_ids.*' => ['integer', 'min:1'],
            'status' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $unitPrice = $this->input('unit_price');
            $unitMinor = $this->input('unit_price_minor');

            if ($unitPrice === null && $unitMinor === null) {
                $validator->errors()->add('unit_price', 'Provide either unit_price or unit_price_minor.');
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
