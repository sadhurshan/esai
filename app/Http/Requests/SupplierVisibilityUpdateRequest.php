<?php

namespace App\Http\Requests;

class SupplierVisibilityUpdateRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'visibility' => ['required', 'string', 'in:private,public'],
        ];
    }
}
