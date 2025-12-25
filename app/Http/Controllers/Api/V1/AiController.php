<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AiServiceUnavailableException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Ai\ForecastRequest;
use App\Http\Requests\Api\Ai\SupplierRiskRequest;
use App\Models\AiEvent;
use App\Models\User;
use App\Services\Admin\AiUsageMetricsService;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiEventRecorder;
use App\Support\ActivePersonaContext;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

class AiController extends ApiController
{
    public function __construct(
        private readonly AiClient $client,
        private readonly AiEventRecorder $recorder,
        private readonly PermissionRegistry $permissionRegistry,
        private readonly AiUsageMetricsService $aiUsageMetrics
    ) {
    }

    public function adminUsageMetrics(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $metrics = $this->aiUsageMetrics->summary($companyId);

        return $this->ok([
            'metrics' => $metrics,
        ], 'AI usage metrics retrieved.');
    }

    public function forecast(ForecastRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];
        $companyId = $context['companyId'];

        if ($this->deniesForecast($user, $companyId)) {
            return $this->fail('You are not authorized to generate forecasts.', Response::HTTP_FORBIDDEN, [
                'code' => 'forecast_forbidden',
            ]);
        }

        $requestPayload = $request->payload();
        $payload = Arr::except($requestPayload, ['entity_type', 'entity_id']);
        $payload['company_id'] = $companyId;
        $metadata = Arr::only($requestPayload, ['entity_type', 'entity_id']);

        $startedAt = microtime(true);

        try {
            $response = $this->client->forecast($payload);
            $latency = $this->calculateLatencyMs($startedAt);
            $this->recordEvent(
                companyId: $companyId,
                userId: $user->id,
                feature: 'forecast',
                requestPayload: $payload,
                responsePayload: $response,
                latencyMs: $latency,
                status: $response['status'] === 'success' ? AiEvent::STATUS_SUCCESS : AiEvent::STATUS_ERROR,
                errorMessage: $response['status'] === 'success' ? null : ($response['errors']['ai'][0] ?? $response['message'] ?? 'Forecast failed.'),
                metadata: $metadata
            );

            return $this->respondFromClient($response);
        } catch (AiServiceUnavailableException $exception) {
            $latency = $this->calculateLatencyMs($startedAt);
            $this->recordEvent(
                companyId: $companyId,
                userId: $user->id,
                feature: 'forecast',
                requestPayload: $payload,
                responsePayload: null,
                latencyMs: $latency,
                status: AiEvent::STATUS_ERROR,
                errorMessage: $exception->getMessage(),
                metadata: $metadata
            );

            return $this->fail('AI service is unavailable.', Response::HTTP_SERVICE_UNAVAILABLE, [
                'service' => ['AI service is unavailable.'],
            ]);
        }
    }

    public function supplierRisk(SupplierRiskRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        $user = $context['user'];
        $companyId = $context['companyId'];

        $requestPayload = $request->payload();

        if ($this->deniesSupplierRisk($user, $companyId, $requestPayload['supplier'] ?? [])) {
            return $this->fail('You are not authorized to view supplier risk.', Response::HTTP_FORBIDDEN, [
                'code' => 'supplier_risk_forbidden',
            ]);
        }

        $payload = Arr::except($requestPayload, ['entity_type', 'entity_id']);
        $payload['company_id'] = $companyId;
        $metadata = Arr::only($requestPayload, ['entity_type', 'entity_id']);

        $startedAt = microtime(true);

        try {
            $response = $this->client->supplierRisk($payload);
            $latency = $this->calculateLatencyMs($startedAt);
            $this->recordEvent(
                companyId: $companyId,
                userId: $user->id,
                feature: 'supplier_risk',
                requestPayload: $payload,
                responsePayload: $response,
                latencyMs: $latency,
                status: $response['status'] === 'success' ? AiEvent::STATUS_SUCCESS : AiEvent::STATUS_ERROR,
                errorMessage: $response['status'] === 'success' ? null : ($response['errors']['ai'][0] ?? $response['message'] ?? 'Supplier risk failed.'),
                metadata: $metadata
            );

            return $this->respondFromClient($response);
        } catch (AiServiceUnavailableException $exception) {
            $latency = $this->calculateLatencyMs($startedAt);
            $this->recordEvent(
                companyId: $companyId,
                userId: $user->id,
                feature: 'supplier_risk',
                requestPayload: $payload,
                responsePayload: null,
                latencyMs: $latency,
                status: AiEvent::STATUS_ERROR,
                errorMessage: $exception->getMessage(),
                metadata: $metadata
            );

            return $this->fail('AI service is unavailable.', Response::HTTP_SERVICE_UNAVAILABLE, [
                'service' => ['AI service is unavailable.'],
            ]);
        }
    }

    private function respondFromClient(array $response): JsonResponse
    {
        if ($response['status'] === 'success') {
            return $this->ok($response['data'], $response['message'] ?? 'AI request completed.');
        }

        return $this->fail($response['message'] ?? 'AI request failed.', Response::HTTP_BAD_GATEWAY, $response['errors'] ?? null);
    }

    private function recordEvent(
        int $companyId,
        int $userId,
        string $feature,
        array $requestPayload,
        ?array $responsePayload,
        ?int $latencyMs,
        string $status,
        ?string $errorMessage,
        array $metadata
    ): void {
        $entityType = $metadata['entity_type'] ?? null;
        $entityId = $metadata['entity_id'] ?? null;

        $this->recorder->record(
            companyId: $companyId,
            userId: $userId,
            feature: $feature,
            requestPayload: $requestPayload,
            responsePayload: $responsePayload,
            latencyMs: $latencyMs,
            status: $status,
            errorMessage: $errorMessage,
            entityType: $entityType,
            entityId: $entityId
        );
    }

    private function calculateLatencyMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function resolveCompanyId(Request $request): ?int
    {
        $personaCompanyId = ActivePersonaContext::companyId();

        if ($personaCompanyId !== null) {
            return $personaCompanyId;
        }

        $user = $request->user();

        if ($user instanceof User && $user->company_id !== null) {
            return (int) $user->company_id;
        }

        return null;
    }

    private function deniesForecast(User $user, int $companyId): bool
    {
        return ! $this->permissionRegistry->userHasAny($user, [
            'forecasts.read',
        ], $companyId);
    }

    /**
     * @param array<string, mixed> $supplierPayload
     */
    private function deniesSupplierRisk(User $user, int $companyId, array $supplierPayload): bool
    {
        $supplierPermissions = ['suppliers.read', 'suppliers.write'];

        $hasBuyerAccess = $this->permissionRegistry->userHasAny($user, [
            'suppliers.read',
            'suppliers.write',
            'rfqs.read',
            'rfqs.write',
        ], $companyId);

        if ($hasBuyerAccess) {
            return false;
        }

        $supplierCompanyId = $supplierPayload['company_id'] ?? null;

        if ($supplierCompanyId === null) {
            return true;
        }

        return ! $this->permissionRegistry->userHasAny($user, $supplierPermissions, (int) $supplierCompanyId);
    }
}
