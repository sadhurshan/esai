<?php

namespace App\Http\Requests\Rfq;

use App\Http\Requests\ApiFormRequest;

class StoreRfqRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', 'in:ready_made,manufacture'],
            'material' => ['nullable', 'string', 'max:120'],
            'method' => ['nullable', 'string', 'max:120'],
            'tolerance_finish' => ['nullable', 'string', 'max:120'],
            'incoterm' => ['nullable', 'string', 'max:8'],
            'currency' => ['nullable', 'string', 'size:3'],
            'open_bidding' => ['sometimes', 'boolean'],
            'due_at' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_name' => ['required', 'string', 'max:160'],
            'items.*.spec' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.uom' => ['nullable', 'string', 'max:16'],
            'items.*.target_price' => ['nullable', 'numeric', 'min:0'],
            'cad_file' => ['nullable', 'file', 'max:10240'], // TODO: clarify with spec - enforce module-specific file size limit
        ];
    }
}
