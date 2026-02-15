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
            'lead_time_days' => ['nullable', 'integer', 'min:1'],
            'lead_time_variance_days' => ['nullable', 'numeric', 'min:0'],
            'entity_type' => ['nullable', 'string', 'max:255'],
            'entity_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{part_id:int,history:array<int,array<string,mixed>>,horizon:int,lead_time_days:?int,lead_time_variance_days:?float,entity_type:?string,entity_id:?int}
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
            'lead_time_days' => isset($validated['lead_time_days']) ? (int) $validated['lead_time_days'] : null,
            'lead_time_variance_days' => isset($validated['lead_time_variance_days'])
                ? (float) $validated['lead_time_variance_days']
                : null,
            'entity_type' => $validated['entity_type'] ?? null,
            'entity_id' => isset($validated['entity_id']) ? (int) $validated['entity_id'] : null,
        ];
    }
}
