<?php

namespace App\Http\Requests;

class StoreInvitationRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'supplier_ids' => ['required', 'array', 'min:1'],
            'supplier_ids.*' => ['integer', 'distinct', 'exists:suppliers,id'],
        ];
    }
}
