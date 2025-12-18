<?php

namespace App\Http\Requests\PurchaseOrder;

use App\Http\Requests\ApiFormRequest;

class SendPurchaseOrderRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:2000'],
            'override_email' => ['nullable', 'email:rfc'],
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
