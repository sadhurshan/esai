<?php

namespace App\Http\Requests\Rfq;

use App\Http\Requests\ApiFormRequest;

class InviteSuppliersRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'supplier_ids' => ['required', 'array', 'min:1'],
            'supplier_ids.*' => ['integer', 'exists:suppliers,id'],
        ];
    }
}
