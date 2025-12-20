<?php

namespace App\Http\Requests\Api\Ai;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class AiWorkflowStartRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'workflow_type' => ['required', 'string', Rule::in(array_keys($this->workflowTemplates()))],
            'rfq_id' => ['nullable', 'string', 'max:64'],
            'goal' => ['nullable', 'string', 'max:2000'],
            'inputs' => ['sometimes', 'array'],
            'user_context' => ['sometimes', 'array'],
        ];
    }

    /**
     * @return array{workflow_type:string,rfq_id:?string,goal:?string,inputs:array<string,mixed>,user_context:array<string,mixed>}
     */
    public function startPayload(): array
    {
        $validated = $this->validated();

        return [
            'workflow_type' => $this->workflowType(),
            'rfq_id' => $this->rfqId(),
            'goal' => $this->normalizeString($validated['goal'] ?? null),
            'inputs' => $this->normalizeAssoc($validated['inputs'] ?? []),
            'user_context' => $this->normalizeAssoc($validated['user_context'] ?? []),
        ];
    }

    public function workflowType(): string
    {
        return (string) $this->validated()['workflow_type'];
    }

    public function rfqId(): ?string
    {
        $value = $this->validated()['rfq_id'] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function workflowTemplates(): array
    {
        $templates = config('ai_workflows.templates', []);

        return is_array($templates) ? $templates : [];
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
