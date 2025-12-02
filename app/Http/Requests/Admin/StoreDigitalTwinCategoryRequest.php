<?php

namespace App\Http\Requests\Admin;

use App\Models\DigitalTwinCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreDigitalTwinCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DigitalTwinCategory::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('digital_twin_categories', 'slug')],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:digital_twin_categories,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');

        $this->merge([
            'slug' => $this->filled('slug') ? Str::slug((string) $slug) : null,
            'parent_id' => $this->filled('parent_id') ? (int) $this->input('parent_id') : null,
        ]);
    }
}
