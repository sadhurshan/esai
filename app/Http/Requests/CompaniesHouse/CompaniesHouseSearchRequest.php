<?php

namespace App\Http\Requests\CompaniesHouse;

use App\Http\Requests\ApiFormRequest;

class CompaniesHouseSearchRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('q')) {
            $this->merge(['q' => trim((string) $this->input('q'))]);
        }
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:160'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ];
    }
}
