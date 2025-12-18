<?php

namespace App\Http\Requests\Supplier\Invoices;

use App\Http\Requests\ApiFormRequest;

class SupplierInvoiceSubmitRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
