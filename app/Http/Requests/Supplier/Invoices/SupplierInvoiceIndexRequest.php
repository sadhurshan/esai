<?php

namespace App\Http\Requests\Supplier\Invoices;

use App\Enums\InvoiceStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SupplierInvoiceIndexRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(InvoiceStatus::values())],
            'po_number' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:120'],
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
