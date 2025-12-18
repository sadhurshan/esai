<?php

namespace App\Http\Requests\Api\Ai;

use App\Http\Requests\ApiFormRequest;

class SupplierRiskRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'supplier' => ['required', 'array'],
            'supplier.id' => ['nullable', 'integer', 'min:1'],
            'supplier.company_id' => ['nullable', 'integer', 'min:1'],
            'supplier.name' => ['nullable', 'string', 'max:255'],
            'entity_type' => ['nullable', 'string', 'max:255'],
            'entity_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{supplier:array<string,mixed>,entity_type:?string,entity_id:?int}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'supplier' => $validated['supplier'],
            'entity_type' => $validated['entity_type'] ?? null,
            'entity_id' => isset($validated['entity_id']) ? (int) $validated['entity_id'] : null,
        ];
    }
}
