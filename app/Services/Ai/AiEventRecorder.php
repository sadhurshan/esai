<?php

namespace App\Services\Ai;

use App\Models\AiEvent;
use App\Support\CompanyContext;

class AiEventRecorder
{
    private const FIELD_CHAR_LIMIT = 10000;

    /**
     * @param array<string, mixed> $requestPayload
     * @param array<string, mixed>|null $responsePayload
     */
    public function record(
        int $companyId,
        ?int $userId,
        string $feature,
        array $requestPayload,
        ?array $responsePayload = null,
        ?int $latencyMs = null,
        string $status = AiEvent::STATUS_SUCCESS,
        ?string $errorMessage = null,
        ?string $entityType = null,
        ?int $entityId = null
    ): AiEvent {
        $payload = [
            'company_id' => $companyId,
            'user_id' => $userId,
            'feature' => $feature,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'request_json' => $this->truncatePayload($requestPayload),
            'response_json' => $this->truncatePayload($responsePayload),
            'latency_ms' => $this->normalizeLatency($latencyMs),
            'status' => $this->normalizeStatus($status),
            'error_message' => $errorMessage,
        ];

        return CompanyContext::forCompany($companyId, static fn (): AiEvent => AiEvent::query()->create($payload));
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower($status);

        return $normalized === AiEvent::STATUS_ERROR
            ? AiEvent::STATUS_ERROR
            : AiEvent::STATUS_SUCCESS;
    }

    private function normalizeLatency(?int $latencyMs): ?int
    {
        if ($latencyMs === null) {
            return null;
        }

        return max(0, $latencyMs);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>|null
     */
    private function truncatePayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $truncated = [];

        foreach ($payload as $key => $value) {
            $truncated[$key] = $this->truncateValue($value);
        }

        return $truncated;
    }

    private function truncateValue(mixed $value): mixed
    {
        if (is_string($value) && mb_strlen($value) > self::FIELD_CHAR_LIMIT) {
            return mb_substr($value, 0, self::FIELD_CHAR_LIMIT);
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $nested) {
                $result[$key] = $this->truncateValue($nested);
            }

            return $result;
        }

        return $value;
    }
}
