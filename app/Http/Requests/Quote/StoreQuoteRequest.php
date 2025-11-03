<?php

namespace App\Http\Requests\Quote;

use App\Http\Requests\ApiFormRequest;

class StoreQuoteRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'rfq_id' => ['required', 'integer', 'exists:rfqs,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'currency' => ['required', 'string', 'size:3'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'min_order_qty' => ['nullable', 'integer', 'min:1'],
            'lead_time_days' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.rfq_item_id' => ['required', 'integer', 'exists:rfq_items,id'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.lead_time_days' => ['required', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:10240'], // TODO: clarify with spec - enforce module-specific file size limit
        ];
    }
}
