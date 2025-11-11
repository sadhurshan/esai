<?php

namespace App\Http\Requests\Localization;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpsertUomConversionRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_code' => ['required', 'string', 'exists:uoms,code'],
            'to_code' => ['required', 'string', 'different:from_code', 'exists:uoms,code'],
            'factor' => ['required', 'numeric', 'gt:0'],
            'offset' => ['nullable', 'numeric'],
        ];
    }
}
