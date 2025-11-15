<?php

namespace App\Http\Requests\PurchaseOrder;

use App\Http\Requests\ApiFormRequest;

class CreatePurchaseOrdersFromAwardsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'award_ids' => ['required', 'array', 'min:1'],
            'award_ids.*' => ['integer', 'exists:rfq_item_awards,id'],
        ];
    }
}
