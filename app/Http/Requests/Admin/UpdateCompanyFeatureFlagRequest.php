<?php

namespace App\Http\Requests\Admin;

use App\Models\Company;
use App\Models\CompanyFeatureFlag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyFeatureFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        $flag = $this->route('flag');

        return $flag instanceof CompanyFeatureFlag
            ? ($this->user()?->can('update', $flag) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $company = $this->route('company');
        $flag = $this->route('flag');

        return [
            'key' => [
                'sometimes',
                'string',
                'max:120',
                Rule::unique('company_feature_flags', 'key')
                    ->where(function ($query) use ($company): void {
                        if ($company instanceof Company) {
                            $query->where('company_id', $company->getKey());
                        }
                    })
                    ->ignore($flag),
            ],
            'value' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
