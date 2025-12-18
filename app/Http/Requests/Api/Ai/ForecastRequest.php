<?php

namespace App\Http\Requests\Api\Ai;

use App\Http\Requests\ApiFormRequest;

class ForecastRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'part_id' => ['required', 'integer', 'min:1'],
            'history' => ['required', 'array', 'min:1'],
            'history.*.date' => ['required', 'date'],
            'history.*.quantity' => ['required', 'numeric'],
            'horizon' => ['required', 'integer', 'min:1', 'max:90'],
            'entity_type' => ['nullable', 'string', 'max:255'],
            'entity_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{part_id:int,history:array<int,array<string,mixed>>,horizon:int,entity_type:?string,entity_id:?int}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'part_id' => (int) $validated['part_id'],
            'history' => array_map(static fn (array $entry): array => [
                'date' => $entry['date'],
                'quantity' => (float) $entry['quantity'],
            ], $validated['history']),
            'horizon' => (int) $validated['horizon'],
            'entity_type' => $validated['entity_type'] ?? null,
            'entity_id' => isset($validated['entity_id']) ? (int) $validated['entity_id'] : null,
        ];
    }
}
