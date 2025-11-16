<?php

namespace App\Http\Resources\Settings;

use App\Enums\DocumentNumberType;
use App\Models\CompanyDocumentNumbering;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NumberingSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $result = [];

        foreach (DocumentNumberType::cases() as $type) {
            $rule = $this->resource[$type->value] ?? null;
            $result[$type->value] = $this->formatRule($rule, $type->value);
        }

        return $result;
    }

    private function formatRule(?CompanyDocumentNumbering $rule, string $type): array
    {
        if ($rule === null) {
            return [
                'prefix' => strtoupper($type) . '-',
                'seq_len' => 4,
                'next' => 1,
                'reset' => 'never',
                'sample' => strtoupper($type) . '-0001',
            ];
        }

        return [
            'prefix' => $rule->prefix ?? '',
            'seq_len' => (int) $rule->seq_len,
            'next' => (int) $rule->next,
            'reset' => $rule->reset?->value ?? 'never',
            'sample' => $rule->computeSample(),
        ];
    }
}
