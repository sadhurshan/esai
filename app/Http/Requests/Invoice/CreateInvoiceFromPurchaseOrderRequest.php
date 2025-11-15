<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateInvoiceFromPurchaseOrderRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxSizeMb = (int) config('documents.max_size_mb', 8);
        $maxSizeKb = $maxSizeMb * 1024;

        return [
            'po_id' => ['nullable', 'integer', Rule::exists('purchase_orders', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where(function ($query): void {
                $companyId = $this->user()?->company_id;

                if ($companyId !== null) {
                    $query->where('company_id', $companyId);
                }
            })],
            'invoice_number' => ['nullable', 'string', 'max:60'],
            'invoice_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'document' => ['nullable', 'file', 'mimes:pdf', 'max:'.$maxSizeKb],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.po_line_id' => ['required', 'integer', 'exists:po_lines,id'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:1', 'required_without:lines.*.qty_invoiced'],
            'lines.*.qty_invoiced' => ['nullable', 'integer', 'min:1', 'required_without:lines.*.quantity'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_price_minor' => ['nullable', 'integer', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:200'],
            'lines.*.uom' => ['nullable', 'string', 'max:16'],
            'lines.*.tax_code_ids' => ['nullable', 'array'],
            'lines.*.tax_code_ids.*' => ['integer', 'exists:tax_codes,id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();

        if (array_key_exists('po_id', $data)) {
            $data['po_id'] = $data['po_id'] !== null ? (int) $data['po_id'] : null;
        }

        if (array_key_exists('supplier_id', $data)) {
            $data['supplier_id'] = $data['supplier_id'] !== null ? (int) $data['supplier_id'] : null;
        }

        $data['lines'] = collect($data['lines'] ?? [])
            ->map(function (array $line): array {
                $quantityRaw = $line['qty_invoiced'] ?? $line['quantity'] ?? null;

                if ($quantityRaw === null) {
                    throw ValidationException::withMessages([
                        'lines' => ['Each line must include qty_invoiced or quantity.'],
                    ]);
                }

                $unitPrice = null;

                if (array_key_exists('unit_price_minor', $line) && $line['unit_price_minor'] !== null) {
                    $unitPrice = ((int) $line['unit_price_minor']) / 100;
                } elseif (array_key_exists('unit_price', $line) && $line['unit_price'] !== null) {
                    $unitPrice = (float) $line['unit_price'];
                }

                return [
                    'po_line_id' => $line['po_line_id'],
                    'quantity' => (int) $quantityRaw,
                    'unit_price' => $unitPrice,
                    'description' => $line['description'] ?? null,
                    'uom' => $line['uom'] ?? null,
                    'tax_code_ids' => $line['tax_code_ids'] ?? [],
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
