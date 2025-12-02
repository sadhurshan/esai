<?php

namespace App\Http\Requests\Library;

use App\Enums\DigitalTwinAssetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DigitalTwinIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $include = $this->normalizeToArray($this->input('include'));
        $tags = $this->normalizeToArray($this->input('tags'));
        $hasAssets = $this->normalizeToArray($this->input('has_assets'));

        if ($include !== null) {
            $this->merge(['include' => $include]);
        }

        if ($tags !== null) {
            $this->merge(['tags' => $tags]);
        }

        if ($hasAssets !== null) {
            $this->merge(['has_assets' => $hasAssets]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $assetTypes = array_map(static fn (DigitalTwinAssetType $type) => $type->value, DigitalTwinAssetType::cases());

        return [
            'q' => ['nullable', 'string', 'max:200'],
            'category_id' => ['nullable', 'integer', 'exists:digital_twin_categories,id'],
            'tag' => ['nullable', 'string', 'max:60'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:60'],
            'has_asset' => ['nullable', 'string', Rule::in($assetTypes)],
            'has_assets' => ['sometimes', 'array'],
            'has_assets.*' => ['string', Rule::in($assetTypes)],
            'updated_from' => ['nullable', 'date'],
            'updated_to' => ['nullable', 'date', 'after_or_equal:updated_from'],
            'sort' => ['nullable', Rule::in(['relevance', 'updated_at', 'title'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'include' => ['sometimes', 'array'],
            'include.*' => [Rule::in(['categories'])],
        ];
    }

    public function validated($key = null, $default = null)
    {
        /** @var array<string, mixed> $data */
        $data = parent::validated($key, $default);

        if (isset($data['has_asset']) && $data['has_asset'] !== null) {
            $data['has_asset'] = strtoupper((string) $data['has_asset']);
        }

        if (isset($data['has_assets']) && is_array($data['has_assets'])) {
            $data['has_assets'] = array_values(array_unique(array_map(static fn ($value) => strtoupper((string) $value), $data['has_assets'])));
        }

        if (isset($data['tags']) && is_array($data['tags'])) {
            $data['tags'] = array_values(array_filter(array_map(static fn ($value) => is_string($value) ? trim($value) : null, $data['tags'])));
        }

        if (isset($data['tag']) && is_string($data['tag'])) {
            $data['tag'] = trim($data['tag']);
        }

        return $data;
    }

    /**
     * @return array<int, string>|null
     */
    private function normalizeToArray(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $parts = array_filter(array_map(static fn ($chunk) => trim($chunk), explode(',', $value)), static fn ($chunk) => $chunk !== '');

            return $parts === [] ? null : array_values($parts);
        }

        return null;
    }
}
