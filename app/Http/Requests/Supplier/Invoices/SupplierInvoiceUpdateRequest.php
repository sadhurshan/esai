<?php

namespace App\Http\Requests\Supplier\Invoices;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SupplierInvoiceUpdateRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_number' => ['nullable', 'string', 'max:60'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'lines' => ['nullable', 'array', 'min:1'],
            'lines.*.id' => ['required_with:lines', 'integer', Rule::exists('invoice_lines', 'id')],
            'lines.*.description' => ['nullable', 'string', 'max:240'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:1'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0.01'],
            'lines.*.tax_code_ids' => ['nullable', 'array'],
            'lines.*.tax_code_ids.*' => ['integer', Rule::exists('tax_codes', 'id')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();

        if (array_key_exists('lines', $data)) {
            $data['lines'] = collect($data['lines'] ?? [])
                ->map(function (array $line): array {
                    $line['tax_code_ids'] = array_values(array_filter(
                        array_map('intval', $line['tax_code_ids'] ?? []),
                        static fn (int $value) => $value > 0
                    ));

                    return $line;
                })
                ->values()
                ->all();
        }

        return $data;
    }
}
