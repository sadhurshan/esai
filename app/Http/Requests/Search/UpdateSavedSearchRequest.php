<?php

namespace App\Http\Requests\Search;

use App\Enums\SearchEntityType;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateSavedSearchRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('entity_types')) {
            $entityTypes = $this->input('entity_types');
            if (is_string($entityTypes)) {
                $this->merge([
                    'entity_types' => $this->splitList($entityTypes),
                ]);
            }
        }

        if ($this->has('filters') && is_array($this->input('filters'))) {
            $filters = $this->input('filters');

            if (isset($filters['status']) && is_string($filters['status'])) {
                $filters['status'] = $this->splitList($filters['status']);
            }

            if (isset($filters['tags']) && is_string($filters['tags'])) {
                $filters['tags'] = $this->splitList($filters['tags']);
            }

            $this->merge(['filters' => $filters]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'q' => ['sometimes', 'string', 'max:255'],
            'entity_types' => ['sometimes', 'array'],
            'entity_types.*' => ['string', Rule::in(SearchEntityType::values())],
            'filters' => ['sometimes', 'nullable', 'array'],
            'filters.status' => ['nullable'],
            'filters.status.*' => ['string', 'max:100'],
            'filters.date_from' => ['nullable', 'date'],
            'filters.date_to' => ['nullable', 'date'],
            'filters.tags' => ['nullable'],
            'filters.tags.*' => ['string', 'max:100'],
            'filters.category' => ['nullable', 'string', 'max:100'],
            'filters.visibility' => ['nullable', 'string', 'max:50'],
            'tags' => ['nullable', 'string', 'max:191'],
        ];
    }

    /**
     * @return list<string>
     */
    private function splitList(string $value): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $item): bool => $item !== ''));

        /** @var list<string> $parts */
        return $parts;
    }
}
