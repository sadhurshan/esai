<?php

namespace App\Http\Requests\Company;

use App\Http\Requests\ApiFormRequest;

class RejectCompanyRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
