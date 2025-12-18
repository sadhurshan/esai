<?php

namespace App\Http\Requests\Supplier\Invoices;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SupplierInvoiceStoreRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxSizeMb = (int) config('documents.max_size_mb', 8);
        $maxSizeKb = $maxSizeMb * 1024;

        return [
            'invoice_number' => ['required', 'string', 'max:60'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'document' => ['nullable', 'file', 'mimes:pdf', 'max:'.$maxSizeKb],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.po_line_id' => ['required', 'integer', Rule::exists('po_lines', 'id')],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0.01'],
            'lines.*.description' => ['nullable', 'string', 'max:240'],
            'lines.*.uom' => ['nullable', 'string', 'max:16'],
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

        $data['lines'] = collect($data['lines'] ?? [])
            ->map(function (array $line): array {
                return [
                    'po_line_id' => (int) $line['po_line_id'],
                    'quantity' => (int) $line['quantity'],
                    'unit_price' => (float) $line['unit_price'],
                    'description' => $line['description'] ?? null,
                    'uom' => $line['uom'] ?? null,
                    'tax_code_ids' => array_values(array_filter(
                        array_map('intval', $line['tax_code_ids'] ?? []),
                        static fn (int $value) => $value > 0
                    )),
                ];
            })
            ->values()
            ->all();

        if ($this->hasFile('document')) {
            $data['document'] = $this->file('document');
        }

        return $data;
    }
}
