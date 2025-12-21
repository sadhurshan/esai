<?php

namespace App\Http\Requests\Admin;

use App\Enums\ScrapedSupplierStatus;
use App\Models\SupplierScrapeJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListScrapedSuppliersRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', 'string', Rule::in(ScrapedSupplierStatus::values())],
            'min_confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'max_confidence' => ['nullable', 'numeric', 'min:0', 'max:1', 'gte:min_confidence'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'cursor' => ['nullable', 'string'],
        ];
    }
}
