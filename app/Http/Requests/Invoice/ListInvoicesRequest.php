<?php

namespace App\Http\Requests\Invoice;

use App\Enums\InvoiceStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class ListInvoicesRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $supplierId = $this->input('supplier_id');

        if ($supplierId === null) {
            return;
        }

        if ($supplierId === '0' || (is_numeric($supplierId) && (int) $supplierId === 0)) {
            $this->merge(['supplier_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(InvoiceStatus::values())],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
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
