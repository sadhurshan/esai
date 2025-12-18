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
