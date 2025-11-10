<?php

namespace App\Http\Requests\DigitalTwin;

use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkAssetProcedureRequest extends FormRequest
{
    private const UNITS = ['day', 'week', 'month', 'year', 'run_hours'];

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
        return [
            'frequency_value' => ['required', 'integer', 'min:1'],
            'frequency_unit' => ['required', 'string', Rule::in(self::UNITS)],
            'last_done_at' => ['sometimes', 'nullable', 'date'],
            'meta' => ['sometimes', 'array'],
        ];
    }
}
