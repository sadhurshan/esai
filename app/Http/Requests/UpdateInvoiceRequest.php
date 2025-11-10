<?php

namespace App\Http\Requests;

class UpdateInvoiceRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:pending,paid,overdue,disputed'],
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
