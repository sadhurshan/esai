<?php

namespace App\Services\Ai\Converters;

use App\Models\AiActionDraft;
use Illuminate\Validation\ValidationException;

abstract class AbstractDraftConverter
{
    /**
     * @return array{output: array<string, mixed>, payload: array<string, mixed>}
     */
    protected function extractOutputAndPayload(AiActionDraft $draft, string $expectedType): array
    {
        if ($draft->action_type !== $expectedType) {
            throw ValidationException::withMessages([
                'action_type' => ['Draft action type does not match the converter.'],
            ]);
        }

        $output = $draft->output_json;

        if (! is_array($output)) {
            throw ValidationException::withMessages([
                'output_json' => ['Action output is missing or malformed.'],
            ]);
        }

        $payload = $output['payload'] ?? null;

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'payload' => ['Action payload is missing or malformed.'],
            ]);
        }

        return ['output' => $output, 'payload' => $payload];
    }

    /**
     * @return array<string, mixed>
     */
    protected function inputs(AiActionDraft $draft): array
    {
        $input = $draft->input_json;
        $payload = is_array($input) ? ($input['inputs'] ?? []) : [];

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array{entity_type:?string,entity_id:?int}
     */
    protected function entityContext(AiActionDraft $draft): array
    {
        $input = $draft->input_json;
        $context = is_array($input) ? ($input['entity_context'] ?? []) : [];

        if (! is_array($context)) {
            return ['entity_type' => null, 'entity_id' => null];
        }

        $entityId = $context['entity_id'] ?? null;

        return [
            'entity_type' => $this->stringValue($context['entity_type'] ?? null),
            'entity_id' => is_numeric($entityId) ? (int) $entityId : null,
        ];
    }

    protected function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function boolValue(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower($value);

            return in_array($value, ['1', 'true', 'yes'], true);
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        return $default;
    }

    /**
     * @return list<string>
     */
    protected function normalizeList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $entry) {
            $text = $this->stringValue($entry ?? null);

            if ($text !== null) {
                $items[] = $text;
            }
        }

        return $items;
    }

    protected function validationError(string $field, string $message): ValidationException
    {
        return ValidationException::withMessages([
            $field => [$message],
        ]);
    }
}
