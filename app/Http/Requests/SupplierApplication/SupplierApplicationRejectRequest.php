<?php

namespace App\Http\Requests\SupplierApplication;

use App\Http\Requests\ApiFormRequest;

class SupplierApplicationRejectRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'max:1000'],
        ];
    }
}
