<?php

namespace App\Http\Requests\Receiving;

use App\Http\Requests\ApiFormRequest;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CompanyStoreGoodsReceiptRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'received_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'posted'])],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.po_line_id' => ['required', 'integer', 'exists:po_lines,id'],
            'lines.*.qty_received' => ['required', 'numeric', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
            'lines.*.uom' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $lines = $this->input('lines', []);

            if (! is_array($lines)) {
                return;
            }

            $hasPositiveQty = false;

            foreach ($lines as $index => $line) {
                $qty = Arr::get($line, 'qty_received');

                if (! is_numeric($qty) || (float) $qty <= 0) {
                    continue;
                }

                $hasPositiveQty = true;
            }

            if (! $hasPositiveQty) {
                $validator->errors()->add('lines', 'At least one line must include a received quantity.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(User $user): array
    {
        $lines = collect($this->input('lines', []))
            ->filter(fn ($line) => is_array($line) && isset($line['po_line_id']))
            ->map(function (array $line): array {
                $received = (float) ($line['qty_received'] ?? 0);
                $receivedQty = (int) round($received);

                return [
                    'purchase_order_line_id' => (int) $line['po_line_id'],
                    'received_qty' => $receivedQty,
                    'accepted_qty' => $receivedQty,
                    'rejected_qty' => 0,
                    'defect_notes' => Arr::get($line, 'notes'),
                    'attachments' => [],
                ];
            })
            ->values()
            ->all();

        return [
            'number' => $this->input('number'),
            'inspected_by_id' => $user->getKey(),
            'inspected_at' => $this->input('received_at'),
            'reference' => $this->input('reference'),
            'notes' => $this->input('notes'),
            'status' => $this->input('status'),
            'lines' => $lines,
        ];
    }
}
