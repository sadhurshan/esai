<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;

class SwitchPersonaRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string'],
        ];
    }
}
