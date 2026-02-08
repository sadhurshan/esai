<?php

namespace App\Http\Requests\CompaniesHouse;

use App\Http\Requests\ApiFormRequest;

class CompaniesHouseProfileRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('company_number')) {
            $this->merge(['company_number' => trim((string) $this->input('company_number'))]);
        }
    }

    public function rules(): array
    {
        return [
            'company_number' => ['required', 'string', 'min:2', 'max:120'],
        ];
    }
}
