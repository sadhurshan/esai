<?php

namespace App\Services\Ai;

use App\Models\AiEvent;
use App\Support\CompanyContext;

class AiEventRecorder
{
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
            'request_json' => $requestPayload,
            'response_json' => $responsePayload,
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
}
