<?php

namespace App\Http\Requests\Api\Ai;

use App\Http\Requests\ApiFormRequest;
use App\Models\AiActionDraft;
use Illuminate\Validation\Rule;

class AiActionPlanRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'action_type' => ['required', 'string', Rule::in(AiActionDraft::ACTION_TYPES)],
            'query' => ['required', 'string', 'max:2000'],
            'inputs' => ['sometimes', 'array'],
            'user_context' => ['sometimes', 'array'],
            'top_k' => ['sometimes', 'integer', 'min:1', 'max:25'],
            'filters' => ['sometimes', 'array'],
            'filters.source_type' => ['sometimes', 'string', 'max:128'],
            'filters.doc_id' => ['sometimes', 'string', 'max:255'],
            'filters.tags' => ['sometimes', 'array'],
            'filters.tags.*' => ['string', 'max:64'],
            'entity_type' => ['nullable', 'string', 'max:64'],
            'entity_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{
     *     action_type:string,
     *     query:string,
     *     inputs:array<string|int, mixed>,
     *     user_context:array<string|int, mixed>,
     *     top_k:int,
     *     filters:array<string, mixed>|null
     * }
     */
    public function actionPayload(): array
    {
        $validated = $this->validated();
        $filters = $this->normalizeFilters($validated['filters'] ?? null);

        return [
            'action_type' => $validated['action_type'],
            'query' => $validated['query'],
            'inputs' => $this->normalizeAssocArray($validated['inputs'] ?? []),
            'user_context' => $this->normalizeAssocArray($validated['user_context'] ?? []),
            'top_k' => isset($validated['top_k']) ? (int) $validated['top_k'] : 8,
            'filters' => $filters,
        ];
    }

    public function actionType(): string
    {
        return $this->validated()['action_type'];
    }

    public function entityType(): ?string
    {
        $value = $this->validated()['entity_type'] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    public function entityId(): ?int
    {
        $value = $this->validated()['entity_id'] ?? null;

        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return array{entity_type:?string,entity_id:?int}
     */
    public function entityContext(): array
    {
        return [
            'entity_type' => $this->entityType(),
            'entity_id' => $this->entityId(),
        ];
    }

    /**
     * @param array<string, mixed>|null $filters
     * @return array<string, mixed>|null
     */
    private function normalizeFilters(?array $filters): ?array
    {
        if ($filters === null) {
            return null;
        }

        $normalized = array_filter([
            'source_type' => isset($filters['source_type']) ? (string) $filters['source_type'] : null,
            'doc_id' => isset($filters['doc_id']) ? (string) $filters['doc_id'] : null,
            'tags' => $this->normalizeTags($filters['tags'] ?? null),
        ], static fn ($value) => $value !== null && $value !== '' && $value !== []);

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @param array<int, string>|null $tags
     * @return list<string>|null
     */
    private function normalizeTags(?array $tags): ?array
    {
        if ($tags === null) {
            return null;
        }

        $values = array_values(array_filter(array_map(static function ($value): ?string {
            if (! is_string($value)) {
                return null;
            }

            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }, $tags)));

        if ($values === []) {
            return null;
        }

        return array_values(array_unique($values));
    }

    private function normalizeAssocArray(?array $payload): array
    {
        return $payload === null ? [] : $payload;
    }
}
