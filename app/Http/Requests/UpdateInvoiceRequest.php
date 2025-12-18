<?php

namespace App\Http\Requests;

use App\Enums\InvoiceStatus;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $statuses = array_values(array_unique(array_merge(InvoiceStatus::values(), ['pending', 'overdue', 'disputed'])));

        return [
            'status' => ['nullable', Rule::in($statuses)],
            'lines' => ['nullable', 'array', 'min:1'],
            'lines.*.id' => ['required_with:lines', 'integer', 'exists:invoice_lines,id'],
            'lines.*.description' => ['nullable', 'string', 'max:200'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:1'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0.01'],
            'lines.*.tax_code_ids' => ['nullable', 'array'],
            'lines.*.tax_code_ids.*' => ['integer'],
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
