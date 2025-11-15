<?php

namespace App\Http\Requests\CreditNote;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCreditNoteLinesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        if (! is_array($lines)) {
            return;
        }

        $normalized = array_map(static function ($line) {
            if (! is_array($line)) {
                return $line;
            }

            return [
                'invoice_line_id' => $line['invoice_line_id'] ?? $line['invoiceLineId'] ?? null,
                'qty_to_credit' => $line['qty_to_credit'] ?? $line['qtyToCredit'] ?? null,
                'description' => $line['description'] ?? null,
                'uom' => $line['uom'] ?? null,
            ];
        }, $lines);

        $this->merge(['lines' => $normalized]);
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.invoice_line_id' => ['required', 'integer'],
            'lines.*.qty_to_credit' => ['required', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.uom' => ['nullable', 'string', 'max:16'],
        ];
    }

    public function attributes(): array
    {
        return [
            'lines.*.invoice_line_id' => 'invoice line',
            'lines.*.qty_to_credit' => 'quantity to credit',
        ];
    }
}
