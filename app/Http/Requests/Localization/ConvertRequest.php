<?php

namespace App\Http\Requests\Localization;

use App\Http\Requests\ApiFormRequest;

class ConvertRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'qty' => ['required', 'numeric', 'gt:0'],
            'from_code' => ['required', 'string', 'exists:uoms,code'],
            'to_code' => ['required', 'string', 'different:from_code', 'exists:uoms,code'],
        ];
    }
}
