<?php

namespace App\Http\Requests\Company;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends ApiFormRequest
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
        $companyId = $this->route('company')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:160', Rule::unique('companies', 'name')->ignore($companyId)],
            'registration_no' => ['sometimes', 'string', 'max:120'],
            'tax_id' => ['sometimes', 'string', 'max:120'],
            'country' => ['sometimes', 'string', 'size:2'],
            'email_domain' => ['sometimes', 'string', 'max:191', 'regex:/^(?!-)(?:[a-z0-9-]+\.)+[a-z]{2,}$/i', Rule::unique('companies', 'email_domain')->ignore($companyId)],
            'primary_contact_name' => ['sometimes', 'string', 'max:160'],
            'primary_contact_email' => ['sometimes', 'email', 'max:191'],
            'primary_contact_phone' => ['sometimes', 'string', 'max:60'],
            'address' => ['sometimes', 'nullable', 'string'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:60'],
            'website' => ['sometimes', 'nullable', 'url', 'max:191'],
            'region' => ['sometimes', 'nullable', 'string', 'max:64'],
            'status' => ['prohibited'],
        ];
    }
}
