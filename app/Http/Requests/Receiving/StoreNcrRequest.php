<?php

namespace App\Http\Requests\Receiving;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreNcrRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'purchase_order_line_id' => ['required', 'integer', 'exists:po_lines,id'],
            'reason' => ['required', 'string', 'max:1000'],
            'disposition' => ['nullable', 'string', Rule::in(['rework', 'return', 'accept_as_is'])],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['integer', 'min:1'],
        ];
    }
}
