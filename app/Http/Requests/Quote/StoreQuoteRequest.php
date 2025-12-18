<?php

namespace App\Http\Requests\Quote;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\InteractsWithDocumentRules;

class StoreQuoteRequest extends ApiFormRequest
{
    use InteractsWithDocumentRules;

    public function rules(): array
    {
        $extensions = $this->documentAllowedExtensions();
        $maxKilobytes = $this->documentMaxKilobytes();

        return [
            'rfq_id' => ['required', 'integer', 'exists:rfqs,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'currency' => ['required', 'string', 'size:3'],
            'incoterm' => ['nullable', 'string', 'max:8'],
            'payment_terms' => ['nullable', 'string', 'max:120'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'min_order_qty' => ['nullable', 'integer', 'min:1'],
            'lead_time_days' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.rfq_item_id' => ['required', 'integer', 'exists:rfq_items,id'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.lead_time_days' => ['required', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:'.$maxKilobytes, 'mimes:'.implode(',', $extensions)],
        ];
    }
}
