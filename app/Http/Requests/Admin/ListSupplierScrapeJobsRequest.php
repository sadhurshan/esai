<?php

namespace App\Http\Requests\Admin;

use App\Enums\SupplierScrapeJobStatus;
use App\Models\SupplierScrapeJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSupplierScrapeJobsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', SupplierScrapeJob::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'status' => ['nullable', 'string', Rule::in(SupplierScrapeJobStatus::values())],
            'query' => ['nullable', 'string', 'max:191'],
            'region' => ['nullable', 'string', 'max:191'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'cursor' => ['nullable', 'string'],
        ];
    }
}
