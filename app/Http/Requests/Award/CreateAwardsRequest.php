<?php

namespace App\Http\Requests\Award;

use App\Http\Requests\ApiFormRequest;
use App\Models\RFQ;
use Illuminate\Support\Facades\Gate;

class CreateAwardsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $rfqId = $this->input('rfq_id');
        $user = $this->user();

        if ($rfqId === null || $user === null) {
            return false;
        }

        $rfq = RFQ::find($rfqId);

        if (! $rfq instanceof RFQ) {
            return false;
        }

        return Gate::forUser($user)->allows('awardLines', $rfq);
    }

    public function rules(): array
    {
        return [
            'rfq_id' => ['required', 'integer', 'exists:rfqs,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.rfq_item_id' => ['required', 'integer', 'exists:rfq_items,id'],
            'items.*.quote_item_id' => ['required', 'integer', 'exists:quote_items,id'],
            'items.*.awarded_qty' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
