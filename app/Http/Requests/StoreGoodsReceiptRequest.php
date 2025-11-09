<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGoodsReceiptRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'number' => [
                'required',
                'string',
                'max:40',
                Rule::unique('goods_receipt_notes', 'number')->where(fn ($query) => $query
                    ->where('company_id', $this->user()?->company_id ?? 0)
                ),
            ],
            'inspected_by_id' => ['nullable', 'integer', 'exists:users,id'],
            'inspected_at' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'integer', 'exists:po_lines,id'],
            'lines.*.received_qty' => ['required', 'integer', 'min:1'],
            'lines.*.accepted_qty' => ['required', 'integer', 'min:0'],
            'lines.*.rejected_qty' => ['required', 'integer', 'min:0'],
            'lines.*.defect_notes' => ['nullable', 'string', 'max:1000'],
            'lines.*.attachments' => ['nullable', 'array', 'max:5'],
            'lines.*.attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }

    /**
     * Ensure accepted and rejected quantities always sum to received.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $lines = $this->input('lines', []);

            if (! is_array($lines)) {
                return;
            }

            foreach ($lines as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }

                $received = $this->toQuantity($line['received_qty'] ?? null);
                $accepted = $this->toQuantity($line['accepted_qty'] ?? null);
                $rejected = $this->toQuantity($line['rejected_qty'] ?? null);

                if (! $this->quantitiesBalanced($received, $accepted, $rejected)) {
                    $validator->errors()->add(
                        "lines.$index.rejected_qty",
                        'Accepted and rejected quantities must equal the received quantity.'
                    );
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->validated();
    }

    private function toQuantity(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function quantitiesBalanced(int $received, int $accepted, int $rejected): bool
    {
        return ($accepted + $rejected) === $received;
    }
}
