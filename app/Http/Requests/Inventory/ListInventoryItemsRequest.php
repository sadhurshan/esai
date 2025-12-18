<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ListInventoryItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string|int>>
     */
    public function rules(): array
    {
        return [
            'cursor' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sku' => ['nullable', 'string', 'max:128'],
            'name' => ['nullable', 'string', 'max:191'],
            'category' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', 'in:active,inactive'],
            'site_id' => ['nullable', 'integer', 'min:1'],
            'below_min' => ['nullable', 'boolean'],
        ];
    }
}
