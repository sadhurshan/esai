<?php

namespace App\Http\Requests\Settings;

use App\Enums\DocumentNumberResetPolicy;
use App\Enums\DocumentNumberType;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateNumberingSettingsRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $rules = [];

        foreach (DocumentNumberType::values() as $type) {
            $rules[$type] = ['sometimes', 'array'];
            $rules["{$type}.prefix"] = ['required_with:' . $type, 'string', 'max:12'];
            $rules["{$type}.seq_len"] = ['required_with:' . $type, 'integer', 'between:3,10'];
            $rules["{$type}.next"] = ['required_with:' . $type, 'integer', 'min:1'];
            $rules["{$type}.reset"] = ['required_with:' . $type, Rule::in(DocumentNumberResetPolicy::values())];
        }

        return $rules;
    }

    public function payload(): array
    {
        $payload = [];

        foreach (DocumentNumberType::values() as $type) {
            if ($this->has($type)) {
                $payload[$type] = $this->sanitizeRule($this->input($type, []));
            }
        }

        return $payload;
    }

    private function sanitizeRule(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $prefix = is_string($value['prefix'] ?? null) ? trim($value['prefix']) : '';

        return [
            'prefix' => $prefix,
            'seq_len' => (int) ($value['seq_len'] ?? 3),
            'next' => max(1, (int) ($value['next'] ?? 1)),
            'reset' => $value['reset'] ?? DocumentNumberResetPolicy::Never->value,
        ];
    }
}
