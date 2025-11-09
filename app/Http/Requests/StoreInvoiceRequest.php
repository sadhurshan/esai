<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id ?? 0;

        return [
            'invoice_number' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('invoices', 'invoice_number')->where(static fn ($query) => $query->where('company_id', $companyId)),
            ],
            'currency' => ['nullable', 'string', 'size:3'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'document' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.po_line_id' => ['required', 'integer', 'exists:po_lines,id'],
            'lines.*.description' => ['nullable', 'string', 'max:200'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.uom' => ['nullable', 'string', 'max:16'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0.01'],
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
