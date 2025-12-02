<?php

namespace App\Http\Requests\Receiving;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateNcrRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['closed'])],
            'disposition' => ['nullable', 'string', Rule::in(['rework', 'return', 'accept_as_is'])],
        ];
    }
}
