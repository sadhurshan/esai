<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryItemRequest extends ApiFormRequest
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
        $itemId = $this->itemId();

        return [
            'sku' => [
                'sometimes',
                'string',
                'min:1',
                'max:128',
                Rule::unique('parts', 'part_number')
                    ->ignore($itemId)
                    ->where(fn ($query) => $query
                        ->where('company_id', $companyId)
                        ->whereNull('deleted_at')
                    ),
            ],
            'name' => ['sometimes', 'string', 'min:1', 'max:191'],
            'uom' => ['sometimes', 'string', 'min:1', 'max:32'],
            'category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string'],
            'min_stock' => ['sometimes', 'nullable', 'numeric', 'gte:0', 'lte:999999999'],
            'reorder_qty' => ['sometimes', 'nullable', 'numeric', 'gte:0', 'lte:999999999'],
            'lead_time_days' => ['sometimes', 'nullable', 'integer', 'between:0,3650'],
            'active' => ['sometimes', 'boolean'],
            'attributes' => ['sometimes', 'nullable', 'array'],
            'attributes.*' => ['nullable', function (string $attribute, mixed $value, callable $fail): void {
                if ($value === null) {
                    return;
                }

                if (! is_string($value) && ! is_numeric($value)) {
                    $fail('The '.$attribute.' must be a string or number.');
                }
            }],
            'default_location_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('sku')) {
            $payload['sku'] = $this->trimString($this->input('sku'));
        }

        if ($this->has('name')) {
            $payload['name'] = $this->trimString($this->input('name'));
        }

        if ($this->has('uom')) {
            $payload['uom'] = $this->trimString($this->input('uom'));
        }

        if ($this->exists('category')) {
            $payload['category'] = $this->nullIfEmpty($this->input('category'));
        }

        if ($this->exists('description')) {
            $payload['description'] = $this->nullIfEmpty($this->input('description'));
        }

        if ($this->exists('default_location_id')) {
            $payload['default_location_id'] = $this->nullIfEmpty($this->input('default_location_id'));
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = [];

        if ($this->has('sku')) {
            $data['sku'] = (string) $this->input('sku');
        }

        if ($this->has('name')) {
            $data['name'] = (string) $this->input('name');
        }

        if ($this->has('uom')) {
            $data['uom'] = (string) $this->input('uom');
        }

        if ($this->exists('category')) {
            $data['category'] = $this->input('category');
        }

        if ($this->exists('description')) {
            $data['description'] = $this->input('description');
        }

        if ($this->exists('default_location_id')) {
            $data['default_location_id'] = $this->input('default_location_id');
        }

        if ($this->exists('attributes')) {
            $data['attributes'] = $this->attributesInput();
        }

        if ($this->has('active')) {
            $data['active'] = $this->boolean('active');
        }

        if ($this->exists('min_stock')) {
            $data['min_stock'] = $this->decimalValue('min_stock');
        }

        if ($this->exists('reorder_qty')) {
            $data['reorder_qty'] = $this->decimalValue('reorder_qty');
        }

        if ($this->exists('lead_time_days')) {
            $data['lead_time_days'] = $this->leadTimeValue('lead_time_days');
        }

        return $data;
    }

    private function itemId(): ?int
    {
        $value = $this->route('item');

        return is_numeric($value) ? (int) $value : null;
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
