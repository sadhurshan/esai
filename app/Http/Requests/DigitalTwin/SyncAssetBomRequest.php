<?php

namespace App\Http\Requests\DigitalTwin;

use App\Models\Asset;
use App\Models\Part;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncAssetBomRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Asset|null $asset */
        $asset = $this->route('asset');

        return $asset !== null && $this->user()?->can('update', $asset);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_id' => ['required', 'integer', Rule::exists(Part::class, 'id')->where('company_id', $companyId)],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.uom' => ['required', 'string', 'max:32'],
            'items.*.criticality' => ['required', 'string', Rule::in(['low', 'medium', 'high'])],
            'items.*.notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
