<?php

namespace App\Http\Requests\Admin;

use App\Enums\CompanyStatus;
use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');

        return $company instanceof Company
            ? ($this->user()?->can('updateStatus', $company) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_map(static fn (CompanyStatus $status) => $status->value, CompanyStatus::cases()))],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function statusEnum(): CompanyStatus
    {
        return CompanyStatus::from($this->validated('status'));
    }
}
