<?php

namespace App\Http\Requests\PurchaseOrder;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SupplierAcknowledgeRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['acknowledged', 'declined'])],
            'reason' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(fn () => $this->input('decision') === 'declined'),
            ],
        ];
    }

    /**
     * @return array{decision:string,reason:?string}
     */
    public function payload(): array
    {
        return $this->validated();
    }
}
