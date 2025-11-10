<?php

namespace App\Http\Requests\Search;

use App\Enums\SearchEntityType;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SearchRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $types = $this->input('types');
        if (is_string($types)) {
            $this->merge([
                'types' => array_values(array_filter(array_map('trim', explode(',', $types)), static fn (string $value): bool => $value !== '')),
            ]);
        }

        $tags = $this->input('tags');
        if (is_string($tags)) {
            $this->merge([
                'tags' => array_values(array_filter(array_map('trim', explode(',', $tags)), static fn (string $value): bool => $value !== '')),
            ]);
        }

        $status = $this->input('status');
        if (is_string($status)) {
            $this->merge([
                'status' => array_values(array_filter(array_map('trim', explode(',', $status)), static fn (string $value): bool => $value !== '')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'max:255'],
            'types' => ['sometimes', 'array'],
            'types.*' => ['string', Rule::in(SearchEntityType::values())],
            'status' => ['nullable'],
            'status.*' => ['string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'tags' => ['nullable'],
            'tags.*' => ['string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'visibility' => ['nullable', 'string', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
