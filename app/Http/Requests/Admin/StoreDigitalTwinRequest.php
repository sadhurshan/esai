<?php

namespace App\Http\Requests\Admin;

use App\Enums\DigitalTwinVisibility;
use App\Models\DigitalTwin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDigitalTwinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DigitalTwin::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:digital_twin_categories,id'],
            'code' => ['nullable', 'string', 'max:64', 'alpha_dash', Rule::unique('digital_twins', 'code')],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'version' => ['nullable', 'string', 'max:32', 'regex:/^\d+\.\d+\.\d+$/'],
            'revision_notes' => ['nullable', 'string', 'max:2000'],
            'visibility' => ['nullable', 'string', Rule::in(array_column(DigitalTwinVisibility::cases(), 'value'))],
            'thumbnail_path' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['string', 'max:64'],
            'specs' => ['nullable', 'array', 'max:100'],
            'specs.*.id' => ['nullable', 'integer', 'exists:digital_twin_specs,id'],
            'specs.*.name' => ['required_with:specs', 'string', 'max:255'],
            'specs.*.value' => ['required_with:specs', 'string', 'max:2000'],
            'specs.*.uom' => ['nullable', 'string', 'max:64'],
            'specs.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
