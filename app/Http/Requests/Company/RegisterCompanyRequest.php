<?php

namespace App\Http\Requests\Company;

use App\Http\Requests\ApiFormRequest;

class RegisterCompanyRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('country')) {
            $this->merge(['country' => strtoupper((string) $this->input('country'))]);
        }

        if ($this->has('email_domain')) {
            $this->merge(['email_domain' => strtolower((string) $this->input('email_domain'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160', 'unique:companies,name'],
            'registration_no' => ['required', 'string', 'max:120'],
            'tax_id' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'size:2'],
            'email_domain' => ['required', 'string', 'max:191', 'regex:/^(?!-)(?:[a-z0-9-]+\.)+[a-z]{2,}$/i', 'unique:companies,email_domain'],
            'primary_contact_name' => ['required', 'string', 'max:160'],
            'primary_contact_email' => ['required', 'email', 'max:191'],
            'primary_contact_phone' => ['required', 'string', 'max:60'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:60'],
            'website' => ['nullable', 'url', 'max:191'],
            'region' => ['nullable', 'string', 'max:64'],
        ];
    }
}
