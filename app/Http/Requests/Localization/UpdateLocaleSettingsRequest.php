<?php

namespace App\Http\Requests\Localization;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateLocaleSettingsRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(['en', 'de', 'fr', 'si'])],
            'timezone' => ['required', 'string', 'timezone'],
            'number_format' => ['required', Rule::in(['system', 'de-DE', 'en-US', 'fr-FR', 'si-LK'])],
            'date_format' => ['required', Rule::in(['system', 'ISO', 'DMY', 'MDY', 'YMD'])],
            'first_day_of_week' => ['required', 'integer', 'between:0,6'],
            'weekend_days' => ['nullable', 'array'],
            'weekend_days.*' => ['integer', 'between:0,6'],
        ];
    }
}
