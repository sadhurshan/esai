<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\ApiFormRequest;

class UpdateCompanySettingsRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'legal_name' => ['sometimes', 'string', 'max:191'],
            'display_name' => ['sometimes', 'string', 'max:191'],
            'tax_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'registration_number' => ['sometimes', 'nullable', 'string', 'max:64'],
            'emails' => ['sometimes', 'array'],
            'emails.*' => ['required', 'email:filter', 'max:191'],
            'phones' => ['sometimes', 'array'],
            'phones.*' => ['required', 'string', 'max:48'],
            'bill_to' => ['sometimes', 'nullable', 'array'],
            'bill_to.attention' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bill_to.line1' => ['required_with:bill_to', 'string', 'max:191'],
            'bill_to.line2' => ['sometimes', 'nullable', 'string', 'max:191'],
            'bill_to.city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bill_to.state' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bill_to.postal_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'bill_to.country' => ['required_with:bill_to', 'string', 'size:2'],
            'ship_from' => ['sometimes', 'nullable', 'array'],
            'ship_from.attention' => ['sometimes', 'nullable', 'string', 'max:120'],
            'ship_from.line1' => ['required_with:ship_from', 'string', 'max:191'],
            'ship_from.line2' => ['sometimes', 'nullable', 'string', 'max:191'],
            'ship_from.city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'ship_from.state' => ['sometimes', 'nullable', 'string', 'max:120'],
            'ship_from.postal_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'ship_from.country' => ['required_with:ship_from', 'string', 'size:2'],
            'logo_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'mark_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merged = [];

        if ($this->exists('emails')) {
            $merged['emails'] = $this->filterStrings($this->input('emails'));
        }

        if ($this->exists('phones')) {
            $merged['phones'] = $this->filterStrings($this->input('phones'));
        }

        if ($this->exists('bill_to')) {
            $merged['bill_to'] = $this->normalizeAddress($this->input('bill_to'));
        }

        if ($this->exists('ship_from')) {
            $merged['ship_from'] = $this->normalizeAddress($this->input('ship_from'));
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    public function payload(): array
    {
        $data = $this->validated();

        if (array_key_exists('bill_to', $data)) {
            $data['bill_to'] = $this->normalizeAddress($data['bill_to']);
        }

        if (array_key_exists('ship_from', $data)) {
            $data['ship_from'] = $this->normalizeAddress($data['ship_from']);
        }

        return $data;
    }

    private function filterStrings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($entry) {
            if (! is_string($entry)) {
                return null;
            }

            $trimmed = trim($entry);

            return $trimmed === '' ? null : $trimmed;
        }, $value)));
    }

    private function normalizeAddress(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        if (! isset($value['line1'], $value['country'])) {
            return null;
        }

        return [
            'attention' => $value['attention'] ?? null,
            'line1' => $value['line1'],
            'line2' => $value['line2'] ?? null,
            'city' => $value['city'] ?? null,
            'state' => $value['state'] ?? null,
            'postal_code' => $value['postal_code'] ?? null,
            'country' => strtoupper((string) $value['country']),
        ];
    }
}
