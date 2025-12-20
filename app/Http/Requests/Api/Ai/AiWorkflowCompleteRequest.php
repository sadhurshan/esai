<?php

namespace App\Http\Requests\Api\Ai;

use App\Http\Requests\ApiFormRequest;

class AiWorkflowCompleteRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'step_index' => ['required', 'integer', 'min:0'],
            'approval' => ['required', 'boolean'],
            'output' => ['sometimes', 'array'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array{step_index:int,approval:bool,output:array<string,mixed>,notes:?string}
     */
    public function completionPayload(): array
    {
        $validated = $this->validated();

        return [
            'step_index' => (int) $validated['step_index'],
            'approval' => (bool) $validated['approval'],
            'output' => $this->normalizeAssoc($validated['output'] ?? []),
            'notes' => $this->normalizeString($validated['notes'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private function normalizeAssoc(?array $payload): array
    {
        return $payload ?? [];
    }

    private function normalizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
