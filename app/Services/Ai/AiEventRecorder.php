<?php

namespace App\Services\Ai;

use App\Models\AiEvent;
use App\Support\CompanyContext;

class AiEventRecorder
{
    private const FIELD_CHAR_LIMIT = 10000;
    public const EVENT_WORKFLOW_START = 'workflow_start';
    public const EVENT_WORKFLOW_STEP_APPROVED = 'workflow_step_approved';
    public const EVENT_WORKFLOW_STEP_REJECTED = 'workflow_step_rejected';
    public const EVENT_WORKFLOW_COMPLETED = 'workflow_completed';
    public const EVENT_WORKFLOW_ABORTED = 'workflow_aborted';
    public const EVENT_WORKFLOW_STEP_READY = 'workflow_step_ready';
    public const EVENT_WORKFLOW_STEP_COMPLETE = 'workflow_step_complete';

    /**
     * @var list<string>
     */
    /**
     * @var list<string>
     */
    private const WORKFLOW_EVENTS = [
        self::EVENT_WORKFLOW_START,
        self::EVENT_WORKFLOW_STEP_APPROVED,
        self::EVENT_WORKFLOW_STEP_REJECTED,
        self::EVENT_WORKFLOW_COMPLETED,
        self::EVENT_WORKFLOW_ABORTED,
        self::EVENT_WORKFLOW_STEP_READY,
        self::EVENT_WORKFLOW_STEP_COMPLETE,
    ];

    /**
     * @return list<string>
     */
    public static function workflowEventKeys(): array
    {
        return self::WORKFLOW_EVENTS;
    }

    /**
     * @param array<string, mixed> $requestPayload
     * @param array<string, mixed>|null $responsePayload
     */
    public function record(
        ?int $companyId,
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

    /**
     * @param array<string, mixed> $workflowContext
     * @param array<string, mixed> $payload
     */
    public function workflowStart(
        ?int $companyId,
        ?int $userId,
        array $workflowContext = [],
        array $payload = [],
        string $status = AiEvent::STATUS_SUCCESS,
        ?string $errorMessage = null
    ): AiEvent {
        return $this->workflowEvent(
            event: self::EVENT_WORKFLOW_START,
            companyId: $companyId,
            userId: $userId,
            workflowContext: $workflowContext,
            payload: $payload,
            status: $status,
            errorMessage: $errorMessage,
        );
    }

    public function workflowStepApproved(
        ?int $companyId,
        ?int $userId,
        array $workflowContext = [],
        array $payload = [],
        string $status = AiEvent::STATUS_SUCCESS,
        ?string $errorMessage = null
    ): AiEvent {
        return $this->workflowEvent(
            event: self::EVENT_WORKFLOW_STEP_APPROVED,
            companyId: $companyId,
            userId: $userId,
            workflowContext: $workflowContext,
            payload: $payload,
            status: $status,
            errorMessage: $errorMessage,
        );
    }

    public function workflowStepRejected(
        ?int $companyId,
        ?int $userId,
        array $workflowContext = [],
        array $payload = [],
        string $status = AiEvent::STATUS_SUCCESS,
        ?string $errorMessage = null
    ): AiEvent {
        return $this->workflowEvent(
            event: self::EVENT_WORKFLOW_STEP_REJECTED,
            companyId: $companyId,
            userId: $userId,
            workflowContext: $workflowContext,
            payload: $payload,
            status: $status,
            errorMessage: $errorMessage,
        );
    }

    public function workflowCompleted(
        ?int $companyId,
        ?int $userId,
        array $workflowContext = [],
        array $payload = [],
        string $status = AiEvent::STATUS_SUCCESS,
        ?string $errorMessage = null
    ): AiEvent {
        return $this->workflowEvent(
            event: self::EVENT_WORKFLOW_COMPLETED,
            companyId: $companyId,
            userId: $userId,
            workflowContext: $workflowContext,
            payload: $payload,
            status: $status,
            errorMessage: $errorMessage,
        );
    }

    public function workflowAborted(
        ?int $companyId,
        ?int $userId,
        array $workflowContext = [],
        array $payload = [],
        string $status = AiEvent::STATUS_SUCCESS,
        ?string $errorMessage = null
    ): AiEvent {
        return $this->workflowEvent(
            event: self::EVENT_WORKFLOW_ABORTED,
            companyId: $companyId,
            userId: $userId,
            workflowContext: $workflowContext,
            payload: $payload,
            status: $status,
            errorMessage: $errorMessage,
        );
    }

    public function workflowStepReady(
        ?int $companyId,
        ?int $userId,
        array $workflowContext = [],
        array $payload = [],
        string $status = AiEvent::STATUS_SUCCESS,
        ?string $errorMessage = null
    ): AiEvent {
        return $this->workflowEvent(
            event: self::EVENT_WORKFLOW_STEP_READY,
            companyId: $companyId,
            userId: $userId,
            workflowContext: $workflowContext,
            payload: $payload,
            status: $status,
            errorMessage: $errorMessage,
        );
    }

    public function workflowStepComplete(
        int $companyId,
        ?int $userId,
        array $workflowContext = [],
        array $payload = [],
        string $status = AiEvent::STATUS_SUCCESS,
        ?string $errorMessage = null
    ): AiEvent {
        return $this->workflowEvent(
            event: self::EVENT_WORKFLOW_STEP_COMPLETE,
            companyId: $companyId,
            userId: $userId,
            workflowContext: $workflowContext,
            payload: $payload,
            status: $status,
            errorMessage: $errorMessage,
        );
    }

    public function workflowEvent(
        string $event,
        int $companyId,
        ?int $userId,
        array $workflowContext = [],
        array $payload = [],
        string $status = AiEvent::STATUS_SUCCESS,
        ?string $errorMessage = null
    ): AiEvent {
        $feature = $this->normalizeWorkflowEventName($event);

        return $this->record(
            companyId: $companyId,
            userId: $userId,
            feature: $feature,
            requestPayload: [
                'event' => $feature,
                'workflow' => $workflowContext,
                'payload' => $payload,
            ],
            responsePayload: null,
            latencyMs: null,
            status: $status,
            errorMessage: $errorMessage,
            entityType: 'ai_workflow',
            entityId: null,
        );
    }

    public function recordWorkflowEvent(
        int $companyId,
        ?int $userId,
        string $event,
        array $workflowContext = [],
        array $payload = [],
        string $status = AiEvent::STATUS_SUCCESS,
        ?string $errorMessage = null
    ): AiEvent {
        return $this->workflowEvent(
            event: $event,
            companyId: $companyId,
            userId: $userId,
            workflowContext: $workflowContext,
            payload: $payload,
            status: $status,
            errorMessage: $errorMessage,
        );
    }

    private function normalizeWorkflowEventName(string $event): string
    {
        $normalized = strtolower(trim($event));

        if ($normalized === '') {
            return 'workflow_event';
        }

        return in_array($normalized, self::WORKFLOW_EVENTS, true)
            ? $normalized
            : $normalized;
    }
}
