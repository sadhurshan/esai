<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryItemRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()?->company_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'sku' => [
                'required',
                'string',
                'min:1',
                'max:128',
                Rule::unique('parts', 'part_number')
                    ->where(fn ($query) => $query
                        ->where('company_id', $companyId)
                        ->whereNull('deleted_at')
                    ),
            ],
            'name' => ['required', 'string', 'min:1', 'max:191'],
            'uom' => ['required', 'string', 'min:1', 'max:32'],
            'category' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'min_stock' => ['nullable', 'numeric', 'gte:0', 'lte:999999999'],
            'reorder_qty' => ['nullable', 'numeric', 'gte:0', 'lte:999999999'],
            'lead_time_days' => ['nullable', 'integer', 'between:0,3650'],
            'active' => ['sometimes', 'boolean'],
            'attributes' => ['nullable', 'array'],
            'attributes.*' => ['nullable', function (string $attribute, mixed $value, callable $fail): void {
                if ($value === null) {
                    return;
                }

                if (! is_string($value) && ! is_numeric($value)) {
                    $fail('The '.$attribute.' must be a string or number.');
                }
            }],
            'default_location_id' => ['nullable', 'string', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => $this->trimString($this->input('sku')),
            'name' => $this->trimString($this->input('name')),
            'uom' => $this->trimString($this->input('uom')),
            'category' => $this->nullIfEmpty($this->input('category')),
            'description' => $this->nullIfEmpty($this->input('description')),
            'default_location_id' => $this->nullIfEmpty($this->input('default_location_id')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'sku' => (string) $this->input('sku'),
            'name' => (string) $this->input('name'),
            'uom' => (string) $this->input('uom'),
            'category' => $this->input('category'),
            'description' => $this->input('description'),
            'attributes' => $this->attributesInput(),
            'default_location_id' => $this->input('default_location_id'),
            'min_stock' => $this->decimalValue('min_stock'),
            'reorder_qty' => $this->decimalValue('reorder_qty'),
            'lead_time_days' => $this->leadTimeValue('lead_time_days'),
            'active' => $this->has('active') ? $this->boolean('active') : true,
        ];
    }

    private function trimString(?string $value): ?string
    {
        return $value === null ? null : trim($value);
    }

    private function nullIfEmpty(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function decimalValue(string $key): ?string
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 3, '.', '');
    }

    private function leadTimeValue(string $key): ?int
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function attributesInput(): ?array
    {
        $attributes = $this->input('attributes');

        return is_array($attributes) ? $attributes : null;
    }
}
