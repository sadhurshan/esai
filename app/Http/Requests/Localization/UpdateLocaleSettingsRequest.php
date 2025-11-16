<?php

namespace App\Http\Requests\Localization;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLocaleSettingsRequest extends ApiFormRequest
{
    private const SUPPORTED_LOCALES = ['en-US', 'en-GB', 'fr-FR', 'de-DE', 'ja-JP', 'zh-CN'];
    private const SUPPORTED_DATE_FORMATS = ['YYYY-MM-DD', 'DD/MM/YYYY', 'MM/DD/YYYY'];
    private const SUPPORTED_NUMBER_FORMATS = ['1,234.56', '1.234,56', '1 234,56'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'string', Rule::in(self::SUPPORTED_LOCALES)],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'number_format' => ['sometimes', Rule::in(self::SUPPORTED_NUMBER_FORMATS)],
            'date_format' => ['sometimes', Rule::in(self::SUPPORTED_DATE_FORMATS)],
            'first_day_of_week' => ['sometimes', 'integer', 'between:0,6'],
            'weekend_days' => ['sometimes', 'array'],
            'weekend_days.*' => ['integer', 'between:0,6'],
            'currency' => ['sometimes', 'array'],
            'currency.primary' => ['required_with:currency', 'string', 'size:3'],
            'currency.display_fx' => ['required_with:currency', 'boolean'],
            'uom' => ['sometimes', 'array'],
            'uom.base_uom' => ['required_with:uom', 'string', 'max:12'],
            'uom.maps' => ['sometimes', 'array'],
            'uom.maps.*' => ['string', 'max:12'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $currency = $this->input('currency');

        if (is_array($currency) && isset($currency['primary'])) {
            $currency['primary'] = strtoupper((string) $currency['primary']);
        }

        $uom = $this->input('uom');

        if (is_array($uom)) {
            if (isset($uom['base_uom'])) {
                $uom['base_uom'] = strtoupper((string) $uom['base_uom']);
            }

            if (isset($uom['maps']) && is_array($uom['maps'])) {
                $normalizedMaps = [];

                foreach ($uom['maps'] as $from => $to) {
                    if (! is_string($from)) {
                        continue;
                    }

                    $normalizedMaps[strtoupper($from)] = is_string($to)
                        ? strtoupper($to)
                        : strtoupper((string) $to);
                }

                $uom['maps'] = $normalizedMaps;
            }
        }

        $this->merge([
            'currency' => $currency,
            'uom' => $uom,
        ]);
    }

    public function withValidator($validator): void
    {
        if (! $validator instanceof Validator) {
            return;
        }

        $validator->after(function (Validator $validator): void {
            $uom = $this->input('uom');

            if (! is_array($uom) || empty($uom['maps']) || ! is_array($uom['maps'])) {
                return;
            }

            $base = strtoupper((string) ($uom['base_uom'] ?? ''));
            $maps = $uom['maps'];
            $sources = [];

            foreach ($maps as $from => $to) {
                if (! is_string($from) || ! is_string($to)) {
                    continue;
                }

                $fromCode = strtoupper($from);
                $toCode = strtoupper($to);

                if ($fromCode === $base) {
                    $validator->errors()->add('uom.maps', 'Base unit cannot be remapped to another target.');
                    break;
                }

                if ($fromCode === $toCode) {
                    $validator->errors()->add('uom.maps', 'Units cannot map to themselves.');
                    break;
                }

                if (in_array($fromCode, $sources, true)) {
                    $validator->errors()->add('uom.maps', 'Duplicate conversion entries detected.');
                    break;
                }

                if (isset($maps[$to]) && strtoupper((string) $maps[$to]) === $fromCode) {
                    $validator->errors()->add('uom.maps', 'Circular unit conversions are not allowed.');
                    break;
                }

                $sources[] = $fromCode;
            }
        });
    }

    public function payload(): array
    {
        $validated = $this->validated();

        $payload = array_filter(
            $validated,
            static fn ($value, $key) => ! in_array($key, ['currency', 'uom'], true),
            ARRAY_FILTER_USE_BOTH
        );

        if (isset($validated['currency'])) {
            $payload['currency_primary'] = $validated['currency']['primary'];
            $payload['currency_display_fx'] = (bool) $validated['currency']['display_fx'];
        }

        if (isset($validated['uom'])) {
            $payload['uom_base'] = $validated['uom']['base_uom'];
            $payload['uom_maps'] = $validated['uom']['maps'] ?? [];
        }

        return $payload;
    }
}
