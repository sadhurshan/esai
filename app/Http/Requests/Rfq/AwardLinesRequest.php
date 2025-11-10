<?php

namespace App\Http\Requests\Rfq;

use App\Http\Requests\ApiFormRequest;
use App\Models\RFQ;
use Illuminate\Support\Facades\Gate;

class AwardLinesRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $rfq = $this->route('rfq');
        $user = $this->user();

        if (! $rfq instanceof RFQ || $user === null) {
            return false;
        }

        return Gate::forUser($user)->allows('awardLines', $rfq);
    }

    public function rules(): array
    {
        return [
            'awards' => ['required', 'array', 'min:1'],
            'awards.*' => ['required', 'array:rfq_item_id,quote_item_id'],
            'awards.*.rfq_item_id' => ['required', 'integer', 'distinct', 'exists:rfq_items,id'],
            'awards.*.quote_item_id' => ['required', 'integer', 'distinct', 'exists:quote_items,id'],
        ];
    }
}
