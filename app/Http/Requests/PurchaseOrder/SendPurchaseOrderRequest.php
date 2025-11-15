<?php

namespace App\Http\Requests\PurchaseOrder;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SendPurchaseOrderRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', 'string', Rule::in(['email', 'webhook'])],
            'to' => ['nullable', 'array'],
            'to.*' => ['required_with:to', 'email:rfc,dns'],
            'cc' => ['nullable', 'array'],
            'cc.*' => ['required_with:cc', 'email:rfc,dns'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->validated();
    }
}
