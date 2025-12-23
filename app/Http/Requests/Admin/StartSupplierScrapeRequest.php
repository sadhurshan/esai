<?php

namespace App\Http\Requests\Admin;

use App\Models\SupplierScrapeJob;
use Illuminate\Foundation\Http\FormRequest;

class StartSupplierScrapeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SupplierScrapeJob::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'query' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'max_results' => ['required', 'integer', 'min:1', 'max:25'],
        ];
    }
}
