<?php

namespace App\Http\Requests\Admin;

use App\Models\DigitalTwinCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDigitalTwinCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('digital_twin_category');

        return $category instanceof DigitalTwinCategory
            ? ($this->user()?->can('update', $category) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('digital_twin_category');
        $categoryId = $category instanceof DigitalTwinCategory ? $category->getKey() : null;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('digital_twin_categories', 'slug')->ignore($categoryId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:digital_twin_categories,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => $this->filled('slug') ? Str::slug((string) $this->input('slug')) : null,
            'parent_id' => $this->filled('parent_id') ? (int) $this->input('parent_id') : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $parentId = $this->input('parent_id');
            $category = $this->route('digital_twin_category');

            if (! $category instanceof DigitalTwinCategory || $parentId === null) {
                return;
            }

            if ($this->createsCircularReference($category, (int) $parentId)) {
                $validator->errors()->add('parent_id', 'Parent category cannot be the category itself or one of its descendants.');
            }
        });
    }

    private function createsCircularReference(DigitalTwinCategory $category, int $parentId): bool
    {
        if ($category->getKey() === $parentId) {
            return true;
        }

        $cursor = DigitalTwinCategory::withTrashed()->find($parentId);

        while ($cursor) {
            if ($cursor->getKey() === $category->getKey()) {
                return true;
            }

            if ($cursor->parent_id === null) {
                break;
            }

            $cursor = DigitalTwinCategory::withTrashed()->find($cursor->parent_id);
        }

        return false;
    }
}
