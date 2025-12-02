<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

class CreateShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'carrier' => ['required', 'string', 'max:120'],
            'tracking_number' => ['required', 'string', 'max:120'],
            'shipped_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.so_line_id' => ['required', 'integer', 'exists:po_lines,id'],
            'lines.*.qty_shipped' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.*.so_line_id.exists' => 'The provided line does not belong to a purchase order.',
        ];
    }

    public function lines(): array
    {
        return $this->input('lines', []);
    }
}
