<?php

namespace App\Http\Requests\Admin;

use App\Models\Company;
use App\Models\CompanyFeatureFlag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyFeatureFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CompanyFeatureFlag::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $company = $this->route('company');

        return [
            'key' => [
                'required',
                'string',
                'max:120',
                Rule::unique('company_feature_flags', 'key')->where(function ($query) use ($company): void {
                    if ($company instanceof Company) {
                        $query->where('company_id', $company->getKey());
                    }
                }),
            ],
            'value' => ['nullable', 'array'],
        ];
    }
}
