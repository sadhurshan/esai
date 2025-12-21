<?php

namespace App\Http\Controllers\Api\Ai;

use App\Exceptions\AiServiceUnavailableException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Ai\CopilotQueryRequest;
use App\Models\AiEvent;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiEventRecorder;
use App\Support\Documents\DocumentAccessPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CopilotSearchController extends ApiController
{
    private const SEARCH_DEFAULT_TOP_K = 8;
    private const ANSWER_DEFAULT_TOP_K = 6;

    public function __construct(
        private readonly AiClient $client,
        private readonly AiEventRecorder $recorder,
        private readonly DocumentAccessPolicy $documentAccessPolicy
    ) {
    }

    public function search(CopilotQueryRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user, 'companyId' => $companyId] = $context;

        $payload = $this->buildPayload($request->validated(), $companyId, self::SEARCH_DEFAULT_TOP_K);
        $startedAt = microtime(true);

        try {
            $response = $this->client->search($payload);
            $response = $this->enforceSearchAccessOnResponse($response, $user, $companyId);

            $status = ($response['status'] ?? 'error') === 'success' ? AiEvent::STATUS_SUCCESS : AiEvent::STATUS_ERROR;
            $errorMessage = $status === AiEvent::STATUS_SUCCESS ? null : ($response['message'] ?? 'Semantic search failed.');

            $this->recordEvent(
                feature: 'search',
                companyId: $companyId,
                userId: $user->id,
                requestPayload: $this->telemetryPayload($payload, $user),
                responsePayload: $this->responseTelemetry('search', $response['data'] ?? null),
                startedAt: $startedAt,
                status: $status,
                errorMessage: $errorMessage
            );

            return $this->respondFromClient($response, 'Search completed.', 'Semantic search failed.');
        } catch (AiServiceUnavailableException $exception) {
            $this->recordEvent(
                feature: 'search',
                companyId: $companyId,
                userId: $user->id,
                requestPayload: $this->telemetryPayload($payload, $user),
                responsePayload: null,
                startedAt: $startedAt,
                status: AiEvent::STATUS_ERROR,
                errorMessage: $exception->getMessage()
            );

            return $this->fail('AI service is unavailable.', Response::HTTP_SERVICE_UNAVAILABLE, [
                'service' => ['AI service is unavailable.'],
            ]);
        } catch (Throwable $exception) {
            $this->recordEvent(
                feature: 'search',
                companyId: $companyId,
                userId: $user->id,
                requestPayload: $this->telemetryPayload($payload, $user),
                responsePayload: null,
                startedAt: $startedAt,
                status: AiEvent::STATUS_ERROR,
                errorMessage: $exception->getMessage()
            );

            throw $exception;
        }
    }

    public function answer(CopilotQueryRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user, 'companyId' => $companyId] = $context;

        $payload = $this->buildPayload(
            $request->validated(),
            $companyId,
            self::ANSWER_DEFAULT_TOP_K,
            includeGeneralFlag: true
        );
        $startedAt = microtime(true);

        try {
            $response = $this->client->answer($payload);
            $status = ($response['status'] ?? 'error') === 'success' ? AiEvent::STATUS_SUCCESS : AiEvent::STATUS_ERROR;

            if ($status === AiEvent::STATUS_SUCCESS) {
                $data = is_array($response['data'] ?? null) ? $response['data'] : [];
                $citations = isset($data['citations']) && is_array($data['citations']) ? $data['citations'] : [];
                $citationReview = $this->documentAccessPolicy->filterCitations($user, $companyId, $citations);

                if ($citationReview['denied'] !== []) {
                    $this->recordEvent(
                        feature: 'answer',
                        companyId: $companyId,
                        userId: $user->id,
                        requestPayload: $this->telemetryPayload($payload, $user),
                        responsePayload: null,
                        startedAt: $startedAt,
                        status: AiEvent::STATUS_ERROR,
                        errorMessage: 'Document access denied.'
                    );

                    return $this->fail('One or more cited documents are not accessible.', Response::HTTP_FORBIDDEN, [
                        'code' => 'document_access_denied',
                        'doc_ids' => $citationReview['denied'],
                    ]);
                }

                $data['citations'] = $citationReview['items'];
                $response['data'] = $data;
            }

            $errorMessage = $status === AiEvent::STATUS_SUCCESS ? null : ($response['message'] ?? 'Answer generation failed.');

            $this->recordEvent(
                feature: 'answer',
                companyId: $companyId,
                userId: $user->id,
                requestPayload: $this->telemetryPayload($payload, $user),
                responsePayload: $this->responseTelemetry('answer', $response['data'] ?? null),
                startedAt: $startedAt,
                status: $status,
                errorMessage: $errorMessage
            );

            return $this->respondFromClient($response, 'Answer generated.', 'Answer generation failed.');
        } catch (AiServiceUnavailableException $exception) {
            $this->recordEvent(
                feature: 'answer',
                companyId: $companyId,
                userId: $user->id,
                requestPayload: $this->telemetryPayload($payload, $user),
                responsePayload: null,
                startedAt: $startedAt,
                status: AiEvent::STATUS_ERROR,
                errorMessage: $exception->getMessage()
            );

            return $this->fail('AI service is unavailable.', Response::HTTP_SERVICE_UNAVAILABLE, [
                'service' => ['AI service is unavailable.'],
            ]);
        } catch (Throwable $exception) {
            $this->recordEvent(
                feature: 'answer',
                companyId: $companyId,
                userId: $user->id,
                requestPayload: $this->telemetryPayload($payload, $user),
                responsePayload: null,
                startedAt: $startedAt,
                status: AiEvent::STATUS_ERROR,
                errorMessage: $exception->getMessage()
            );

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function buildPayload(
        array $validated,
        int $companyId,
        int $defaultTopK,
        bool $includeGeneralFlag = false,
    ): array
    {
        $query = trim((string) ($validated['query'] ?? ''));
        $topK = (int) ($validated['top_k'] ?? $defaultTopK);
        $filters = $this->normalizeFilters($validated['filters'] ?? []);
        $allowGeneralValue = $validated['allow_general'] ?? false;
        $allowGeneral = is_bool($allowGeneralValue)
            ? $allowGeneralValue
            : (filter_var($allowGeneralValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false);
        $payload = [
            'company_id' => $companyId,
            'query' => $query,
            'top_k' => $topK,
            'filters' => $filters,
        ];

        if ($includeGeneralFlag) {
            $payload['allow_general'] = $allowGeneral;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed>|null $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters($filters): array
    {
        if (! is_array($filters)) {
            return [];
        }

        $normalized = [];

        $sourceType = trim((string) ($filters['source_type'] ?? ''));
        if ($sourceType !== '') {
            $normalized['source_type'] = $sourceType;
        }

        $docId = $filters['doc_id'] ?? null;
        if ($docId !== null && $docId !== '') {
            $normalized['doc_id'] = (string) $docId;
        }

        $docVersion = $filters['doc_version'] ?? null;
        if ($docVersion !== null && $docVersion !== '') {
            $normalized['doc_version'] = (string) $docVersion;
        }

        $tags = $filters['tags'] ?? [];
        if (is_array($tags)) {
            $normalizedTags = [];
            foreach ($tags as $tag) {
                $tagValue = trim((string) $tag);
                if ($tagValue !== '') {
                    $normalizedTags[] = $tagValue;
                }
            }

            if ($normalizedTags !== []) {
                $normalized['tags'] = array_values(array_unique($normalizedTags));
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function enforceSearchAccessOnResponse(array $response, User $user, int $companyId): array
    {
        if (($response['status'] ?? 'error') !== 'success') {
            return $response;
        }

        $data = $response['data'] ?? null;

        if (! is_array($data) || ! isset($data['hits']) || ! is_array($data['hits'])) {
            return $response;
        }

        $review = $this->documentAccessPolicy->filterSearchHits($user, $companyId, $data['hits']);

        if ($review['denied'] !== []) {
            Log::info('copilot_search_hits_filtered', [
                'company_id' => $companyId,
                'user_id' => $user->id,
                'denied_doc_ids' => $review['denied'],
            ]);
        }

        $data['hits'] = $review['items'];
        $response['data'] = $data;

        return $response;
    }

    private function respondFromClient(array $response, string $successMessage, string $errorMessage): JsonResponse
    {
        if (($response['status'] ?? 'error') === 'success') {
            return $this->ok($response['data'] ?? null, $response['message'] ?? $successMessage);
        }

        return $this->fail($response['message'] ?? $errorMessage, Response::HTTP_BAD_GATEWAY, $response['errors'] ?? null);
    }

    private function recordEvent(
        string $feature,
        int $companyId,
        int $userId,
        array $requestPayload,
        ?array $responsePayload,
        float $startedAt,
        string $status,
        ?string $errorMessage
    ): void {
        $this->recorder->record(
            companyId: $companyId,
            userId: $userId,
            feature: $feature,
            requestPayload: $requestPayload,
            responsePayload: $responsePayload,
            latencyMs: $this->calculateLatencyMs($startedAt),
            status: $status,
            errorMessage: $errorMessage,
        );
    }

    private function calculateLatencyMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function telemetryPayload(array $payload, User $user): array
    {
        $filters = $payload['filters'] ?? [];

        $tags = [];
        if (isset($filters['tags']) && is_array($filters['tags'])) {
            $tags = array_slice($filters['tags'], 0, 5);
        }

        return [
            'company_id' => $payload['company_id'] ?? null,
            'user_id' => $user->id,
            'user_role' => $user->role,
            'query_preview' => Str::limit((string) ($payload['query'] ?? ''), 200, '...'),
            'top_k' => $payload['top_k'] ?? null,
            'allow_general' => (bool) ($payload['allow_general'] ?? false),
            'filters' => [
                'source_type' => $filters['source_type'] ?? null,
                'doc_id' => $filters['doc_id'] ?? null,
                'doc_version' => $filters['doc_version'] ?? null,
                'tag_count' => isset($filters['tags']) && is_array($filters['tags']) ? count($filters['tags']) : 0,
                'tags_preview' => $tags,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function responseTelemetry(string $feature, ?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        if ($feature === 'search') {
            $hits = [];
            if (isset($data['hits']) && is_array($data['hits'])) {
                foreach (array_slice($data['hits'], 0, 5) as $hit) {
                    if (! is_array($hit)) {
                        continue;
                    }

                    $hits[] = [
                        'doc_id' => $hit['doc_id'] ?? null,
                        'doc_version' => $hit['doc_version'] ?? null,
                        'chunk_id' => $hit['chunk_id'] ?? null,
                        'score' => $hit['score'] ?? null,
                        'title' => Str::limit((string) ($hit['title'] ?? ''), 80, '...'),
                    ];
                }
            }

            return [
                'hits_preview' => $hits,
                'total_hits' => isset($data['hits']) && is_array($data['hits']) ? count($data['hits']) : 0,
            ];
        }

        if ($feature === 'answer') {
            $citationsPreview = [];
            $citations = isset($data['citations']) && is_array($data['citations']) ? $data['citations'] : [];

            foreach (array_slice($citations, 0, 5) as $citation) {
                if (! is_array($citation)) {
                    continue;
                }

                $citationsPreview[] = [
                    'doc_id' => $citation['doc_id'] ?? null,
                    'doc_version' => $citation['doc_version'] ?? null,
                    'chunk_id' => $citation['chunk_id'] ?? null,
                    'score' => $citation['score'] ?? null,
                    'snippet' => Str::limit((string) ($citation['snippet'] ?? ''), 120, '...'),
                ];
            }

            return [
                'answer_preview' => Str::limit((string) ($data['answer'] ?? ''), 400, '...'),
                'citation_count' => count($citations),
                'citations_preview' => $citationsPreview,
            ];
        }

        return $data;
    }
}
