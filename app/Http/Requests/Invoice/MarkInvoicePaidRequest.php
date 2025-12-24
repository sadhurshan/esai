<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\ApiFormRequest;

class MarkInvoicePaidRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_reference' => ['required', 'string', 'max:191'],
            'note' => ['nullable', 'string', 'max:1000'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_currency' => ['nullable', 'string', 'size:3'],
            'payment_method' => ['nullable', 'string', 'max:120'],
            'paid_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();

        if (! empty($data['payment_currency'])) {
            $data['payment_currency'] = strtoupper($data['payment_currency']);
        }

        return $data;
    }
}
