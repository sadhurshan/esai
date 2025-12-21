<?php

namespace App\Services\Ai;

use App\Exceptions\AiServiceUnavailableException;
use App\Models\AiEvent;
use App\Models\CompanyAiSetting;
use App\Services\Ai\AiEventRecorder;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AiClient
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly CacheRepository $cache,
        private readonly AiEventRecorder $recorder,
        private readonly ?Request $request = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:string,message:string,data:array<string, mixed>|null,errors:array<string, mixed>}
     */
    public function forecast(array $payload): array
    {
        return $this->send('forecast', $payload, 'Forecast generated.', 'forecast');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:string,message:string,data:array<string, mixed>|null,errors:array<string, mixed>}
     */
    public function supplierRisk(array $payload): array
    {
        return $this->send('supplier-risk', $payload, 'Supplier risk assessed.', 'supplier_risk');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:string,message:string,data:array<string, mixed>|null,errors:array<string, mixed>}
     */
    public function indexDocument(array $payload): array
    {
        return $this->send('index/document', $payload, 'Document indexed.', 'index_document');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:string,message:string,data:array<string, mixed>|null,errors:array<string, mixed>}
     */
    public function search(array $payload): array
    {
        return $this->send('search', $payload, 'Search completed.', 'search');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:string,message:string,data:array<string, mixed>|null,errors:array<string, mixed>}
     */
    public function answer(array $payload): array
    {
        return $this->send(
            'answer',
            $payload,
            'Answer generated.',
            'answer',
            function (array $enrichedPayload): array {
                return $this->applyLlmProviderControls($enrichedPayload);
            }
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:string,message:string,data:array<string, mixed>|null,errors:array<string, mixed>}
     */
    public function chatRespond(array $payload): array
    {
        return $this->send(
            'chat/respond',
            $payload,
            'Chat response generated.',
            'chat_respond',
            function (array $enrichedPayload): array {
                return $this->applyLlmProviderControls($enrichedPayload);
            }
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param callable(array):void $listener
     */
    public function chatRespondStream(array $payload, callable $listener): void
    {
        $this->sendStream(
            'chat/respond_stream',
            $payload,
            'chat_respond_stream',
            $listener
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:string,message:string,data:array<string, mixed>|null,errors:array<string, mixed>}
     */
    public function chatContinue(array $payload): array
    {
        return $this->send(
            'chat/continue',
            $payload,
            'Chat response continued.',
            'chat_continue',
            function (array $enrichedPayload): array {
                return $this->applyLlmProviderControls($enrichedPayload);
            }
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:string,message:string,data:array<string, mixed>|null,errors:array<string, mixed>}
     */
    public function planAction(array $payload): array
    {
        return $this->send(
            'actions/plan',
            $payload,
            'Action draft generated.',
            'copilot_action_plan',
            function (array $enrichedPayload): array {
                return $this->applyLlmProviderControls($enrichedPayload);
            },
            function (Response $response, string $successMessage): array {
                return $this->formatActionPlanResponse($response, $successMessage);
            }
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:string,message:string,data:array<string, mixed>|null,errors:array<string, mixed>}
     */
    public function planWorkflow(array $payload): array
    {
        return $this->send('workflows/plan', $payload, 'Workflow planned.', 'workflow_plan');
    }

    /**
     * @return array{status:string,message:string,data:array<string, mixed>|null,errors:array<string, mixed>}
     */
    public function nextWorkflowStep(string $workflowId): array
    {
        return $this->send(
            sprintf('workflows/%s/next', $workflowId),
            [],
            'Workflow step fetched.',
            'workflow_next',
            null,
            null,
            'get'
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:string,message:string,data:array<string, mixed>|null,errors:array<string, mixed>}
     */
    public function completeWorkflowStep(string $workflowId, array $payload): array
    {
        return $this->send(
            sprintf('workflows/%s/complete', $workflowId),
            $payload,
            'Workflow step completed.',
            'workflow_complete'
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param callable|null $payloadInterceptor
     * @param callable|null $responseFormatter
     * @return array{status:string,message:string,data:array<string, mixed>|null,errors:array<string, mixed>}
     */
    private function send(
        string $endpoint,
        array $payload,
        string $successMessage,
        string $feature,
        ?callable $payloadInterceptor = null,
        ?callable $responseFormatter = null,
        string $method = 'post'
    ): array
    {
        if (! $this->isEnabled()) {
            return $this->disabledResponse();
        }

        if ($this->isCircuitOpen()) {
            $this->recordCircuitSkip($feature, $payload);

            return $this->circuitUnavailableResponse();
        }

        $httpMethod = strtolower($method);
        $enrichedPayload = $httpMethod === 'get' ? $payload : $this->enrichPayload($payload);

        if ($payloadInterceptor !== null) {
            $enrichedPayload = $payloadInterceptor($enrichedPayload);
        }

        try {
            $request = $this->pendingRequest();

            $response = $httpMethod === 'get'
                ? $request->get(ltrim($endpoint, '/'), $enrichedPayload)
                : $request->post(ltrim($endpoint, '/'), $enrichedPayload);
        } catch (ConnectionException $exception) {
            $this->recordFailure($feature, $enrichedPayload, $exception->getMessage());

            throw new AiServiceUnavailableException('AI service is unavailable.', 0, $exception);
        }

        if ($responseFormatter !== null) {
            $result = $responseFormatter($response, $successMessage);
        } else {
            $result = $this->formatResponse($response, $successMessage);
        }

        if ($result['status'] === 'success') {
            $this->resetFailureWindow();
        } else {
            $this->recordFailure($feature, $enrichedPayload, $result['message'] ?? null);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @param callable(array):void $listener
     */
    private function sendStream(
        string $endpoint,
        array $payload,
        string $feature,
        callable $listener
    ): void {
        if (! $this->isEnabled()) {
            throw new AiServiceUnavailableException('AI service is disabled.');
        }

        if ($this->isCircuitOpen()) {
            $this->recordCircuitSkip($feature, $payload);

            throw new AiServiceUnavailableException('AI circuit breaker is open.');
        }

        $enrichedPayload = $this->applyLlmProviderControls($this->enrichPayload($payload));

        try {
            $request = $this->pendingRequest()
                ->accept('text/event-stream')
                ->withOptions(['stream' => true]);

            $response = $request->post(ltrim($endpoint, '/'), $enrichedPayload);
        } catch (ConnectionException $exception) {
            $this->recordFailure($feature, $enrichedPayload, $exception->getMessage());

            throw new AiServiceUnavailableException('AI service is unavailable.', 0, $exception);
        }

        if ($response->failed()) {
            $body = $response->body() ?: '';
            $status = $response->status();
            $reason = trim((string) ($response->reason() ?? ''));
            $statusSummary = trim(sprintf('%s %s', $status, $reason));
            $bodyPreview = $body === '' ? '' : Str::limit($body, 500);

            $this->recordFailure($feature, $enrichedPayload, $body !== '' ? $body : null);

            $details = $statusSummary;

            if ($bodyPreview !== '') {
                $details = $details === ''
                    ? $bodyPreview
                    : sprintf('%s - %s', $details, $bodyPreview);
            }

            throw new AiServiceUnavailableException(
                $details === ''
                    ? 'AI streaming request failed.'
                    : sprintf('AI streaming request failed (%s).', $details)
            );
        }

        $stream = $response->toPsrResponse()->getBody();
        $this->streamResponseBody($stream, $listener);
        $this->resetFailureWindow();
    }

    /**
     * @param \Psr\Http\Message\StreamInterface $stream
     * @param callable(array):void $listener
     */
    private function streamResponseBody($stream, callable $listener): void
    {
        $buffer = '';

        while (! $stream->eof()) {
            $chunk = $stream->read(1024);

            if ($chunk === false || $chunk === '') {
                usleep(50000);

                continue;
            }

            $buffer .= str_replace("\r", '', $chunk);

            while (($delimiterPosition = strpos($buffer, "\n\n")) !== false) {
                $rawEvent = substr($buffer, 0, $delimiterPosition);
                $buffer = substr($buffer, $delimiterPosition + 2) ?: '';
                $event = $this->parseSseEvent($rawEvent);

                if ($event !== null) {
                    $listener($event);
                }
            }
        }

        $buffer = trim($buffer);

        if ($buffer !== '') {
            $event = $this->parseSseEvent($buffer);

            if ($event !== null) {
                $listener($event);
            }
        }
    }

    /**
     * @return array{event:string,data:mixed,frame:string}|null
     */
    private function parseSseEvent(string $rawEvent): ?array
    {
        $normalized = trim($rawEvent);

        if ($normalized === '') {
            return null;
        }

        $eventName = 'message';
        $dataLines = [];

        foreach (preg_split('/\n/', $normalized) as $line) {
            if ($line === '' || str_starts_with($line, ':')) {
                continue;
            }

            if (str_starts_with($line, 'event:')) {
                $eventName = trim(substr($line, 6)) ?: 'message';

                continue;
            }

            if (str_starts_with($line, 'data:')) {
                $dataLines[] = ltrim(substr($line, 5));
            }
        }

        $dataPayload = implode("\n", $dataLines);
        $decoded = json_decode($dataPayload, true);
        $data = json_last_error() === JSON_ERROR_NONE ? $decoded : $dataPayload;

        return [
            'event' => $eventName,
            'data' => $data,
            'frame' => $normalized . "\n\n",
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function enrichPayload(array $payload): array
    {
        $companyId = $this->currentCompanyId();
        $user = $this->request?->user();

        if ($user === null) {
            $user = auth()->user();
        }

        $userId = $this->currentUserId();
        $role = $this->resolveUserRole($user);
        $safetyIdentifier = $this->resolveSafetyIdentifier($user);

        $auditContext = array_filter([
            'company_id' => $companyId,
            'user_id' => $userId,
            'role' => $role,
        ], static fn ($value) => $value !== null && $value !== '');

        if ($auditContext !== []) {
            $payload['audit_context'] = $auditContext;
        }

        if ($safetyIdentifier !== null) {
            $payload['safety_identifier'] = $safetyIdentifier;
        }

        if (! isset($payload['company_id']) && $companyId !== null) {
            $payload['company_id'] = $companyId;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function applyLlmProviderControls(array $payload): array
    {
        $companyId = $this->extractCompanyId($payload);
        $provider = $this->resolveLlmProviderForCompany($companyId);

        $payload['llm_provider'] = $provider;
        $payload['llm_answers_enabled'] = $provider === 'openai';

        return $payload;
    }

    private function extractCompanyId(array $payload): ?int
    {
        $companyId = $payload['company_id'] ?? null;

        if ($companyId === null) {
            return null;
        }

        if (is_numeric($companyId)) {
            $value = (int) $companyId;

            return $value > 0 ? $value : null;
        }

        return null;
    }

    private function resolveLlmProviderForCompany(?int $companyId): string
    {
        if ($companyId === null) {
            return 'dummy';
        }

        $setting = CompanyAiSetting::query()
            ->select(['llm_answers_enabled', 'llm_provider'])
            ->where('company_id', $companyId)
            ->first();

        if (! $setting instanceof CompanyAiSetting) {
            return 'dummy';
        }

        return $setting->resolvedProvider();
    }

    private function resolveUserRole(?AuthenticatableContract $user): ?string
    {
        $role = $this->extractUserAttribute($user, 'role');

        return is_string($role) && $role !== '' ? $role : null;
    }

    private function resolveSafetyIdentifier(?AuthenticatableContract $user = null): ?string
    {
        $identifier = $this->extractUserAttribute($user, 'email');

        if (! is_string($identifier) || $identifier === '') {
            $identifier = $this->extractAuthIdentifier($user);
        }

        if ($identifier === null) {
            return null;
        }

        $appKey = (string) config('app.key');

        return hash('sha256', sprintf('%s|%s', $appKey, $identifier));
    }

    private function extractUserAttribute(?AuthenticatableContract $user, string $attribute): mixed
    {
        $candidate = $user;

        if ($candidate === null) {
            $candidate = auth()->user();
        }

        if ($candidate === null) {
            return null;
        }

        if (method_exists($candidate, 'getAttribute')) {
            return $candidate->getAttribute($attribute);
        }

        if (isset($candidate->{$attribute})) {
            return $candidate->{$attribute};
        }

        return null;
    }

    private function extractAuthIdentifier(?AuthenticatableContract $user = null): ?string
    {
        $candidate = $user;

        if ($candidate === null) {
            $candidate = auth()->user();
        }

        if ($candidate === null) {
            return null;
        }

        $identifier = $candidate->getAuthIdentifier();

        if (is_numeric($identifier)) {
            return (string) $identifier;
        }

        return is_string($identifier) && $identifier !== '' ? $identifier : null;
    }

    private function pendingRequest(): PendingRequest
    {
        return $this->http
            ->baseUrl($this->baseUrl())
            ->acceptJson()
            ->timeout($this->timeoutSeconds())
            ->withHeaders($this->headers());
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $sharedSecret = $this->sharedSecret();

        if ($sharedSecret === null || $sharedSecret === '') {
            throw new AiServiceUnavailableException('AI shared secret is not configured.');
        }

        $headers = [
            'X-AI-Secret' => $sharedSecret,
            'X-Request-Id' => $this->resolveRequestId(),
        ];

        return array_filter($headers, static fn (?string $value): bool => $value !== null && $value !== '');
    }

    private function resolveRequestId(): string
    {
        $request = $this->request ?? request();

        $headerId = $request?->headers->get('X-Request-Id');
        if (is_string($headerId) && $headerId !== '') {
            return $headerId;
        }

        $attributeId = $request?->attributes->get('request_id');
        if (is_string($attributeId) && $attributeId !== '') {
            return $attributeId;
        }

        return (string) Str::uuid();
    }

    private function formatResponse(Response $response, string $successMessage): array
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $successful = $response->successful();

        return [
            'status' => $this->normalizeStatus($body['status'] ?? null, $successful),
            'message' => $this->resolveMessage($body, $successful, $successMessage),
            'data' => $this->resolveData($body),
            'errors' => $this->normalizeErrors($body, $successful),
        ];
    }

    private function formatActionPlanResponse(Response $response, string $successMessage): array
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        if ($response->successful()) {
            return [
                'status' => 'success',
                'message' => $successMessage,
                'data' => $body,
                'errors' => [],
            ];
        }

        $message = $body['message'] ?? $body['detail'] ?? 'Failed to generate action draft.';
        $message = is_string($message) && $message !== '' ? $message : 'Failed to generate action draft.';

        return [
            'status' => 'error',
            'message' => $message,
            'data' => null,
            'errors' => $this->normalizeErrors($body, false),
        ];
    }

    private function resolveMessage(array $body, bool $successful, string $successMessage): string
    {
        if (isset($body['message']) && is_string($body['message']) && $body['message'] !== '') {
            return $body['message'];
        }

        if (! $successful) {
            $detail = $body['detail'] ?? null;

            return is_string($detail) && $detail !== '' ? $detail : 'AI request failed.';
        }

        return $successMessage;
    }

    private function resolveData(array $body): ?array
    {
        $data = $body['data'] ?? null;

        return is_array($data) ? $data : null;
    }

    private function normalizeErrors(array $body, bool $successful): array
    {
        if ($successful) {
            return [];
        }

        $errors = $body['errors'] ?? [];

        if (is_array($errors) && $errors !== []) {
            return $errors;
        }

        $detail = $body['detail'] ?? $body['message'] ?? 'AI request failed.';
        $detail = is_string($detail) && $detail !== '' ? $detail : 'AI request failed.';

        return [
            'ai' => [$detail],
        ];
    }

    private function normalizeStatus(?string $status, bool $successful): string
    {
        $normalized = is_string($status) ? strtolower($status) : null;

        return match ($normalized) {
            'ok', 'success' => 'success',
            'error', 'failed', 'fail' => 'error',
            default => $successful ? 'success' : 'error',
        };
    }

    private function isEnabled(): bool
    {
        return (bool) config('ai.enabled', false);
    }

    private function baseUrl(): string
    {
        $baseUrl = rtrim((string) config('ai.base_url', ''), '/');

        return $baseUrl !== '' ? $baseUrl : 'http://localhost:8001';
    }

    private function timeoutSeconds(): int
    {
        $timeout = (int) config('ai.timeout_seconds', 15);

        return $timeout > 0 ? $timeout : 15;
    }

    private function sharedSecret(): ?string
    {
        $secret = config('ai.shared_secret');

        return is_string($secret) ? trim($secret) : null;
    }

    private function disabledResponse(): array
    {
        return [
            'status' => 'error',
            'message' => 'AI service is disabled.',
            'data' => null,
            'errors' => [
                'service' => ['AI service is disabled.'],
            ],
        ];
    }

    private function circuitUnavailableResponse(): array
    {
        return [
            'status' => 'error',
            'message' => 'AI temporarily unavailable. Please retry shortly.',
            'data' => null,
            'errors' => [
                'service' => ['AI circuit breaker is open.'],
            ],
        ];
    }

    private function isCircuitBreakerEnabled(): bool
    {
        return (bool) config('ai.circuit_breaker.enabled', true);
    }

    private function isCircuitOpen(): bool
    {
        if (! $this->isCircuitBreakerEnabled()) {
            return false;
        }

        $openUntil = $this->cache->get($this->circuitOpenCacheKey());

        if (! is_int($openUntil)) {
            return false;
        }

        $now = Carbon::now()->timestamp;

        if ($openUntil <= $now) {
            $this->closeCircuit();

            return false;
        }

        return true;
    }

    private function recordFailure(string $feature, array $payload, ?string $errorMessage): void
    {
        if (! $this->isCircuitBreakerEnabled()) {
            return;
        }

        $key = $this->failureCacheKey();
        $windowSeconds = $this->circuitBreakerWindowSeconds();
        $now = Carbon::now()->timestamp;
        $windowStart = $now - $windowSeconds;

        $failures = $this->cache->get($key, []);
        $failures = is_array($failures) ? $failures : [];
        $failures[] = $now;

        $failures = array_values(array_filter($failures, static function ($timestamp) use ($windowStart): bool {
            return is_int($timestamp) && $timestamp >= $windowStart;
        }));

        $this->cache->put($key, $failures, $windowSeconds);

        if (count($failures) >= $this->circuitFailureThreshold()) {
            $this->openCircuit($feature, $payload, $errorMessage);
        }
    }

    private function resetFailureWindow(): void
    {
        if (! $this->isCircuitBreakerEnabled()) {
            return;
        }

        $this->cache->forget($this->failureCacheKey());
    }

    private function openCircuit(string $feature, array $payload, ?string $errorMessage): void
    {
        if (! $this->isCircuitBreakerEnabled()) {
            return;
        }

        $key = $this->circuitOpenCacheKey();
        $previouslyOpenUntil = $this->cache->get($key);
        $openSeconds = $this->circuitOpenSeconds();
        $now = Carbon::now();
        $openUntil = $now->copy()->addSeconds($openSeconds)->timestamp;
        $nowTimestamp = $now->timestamp;

        $this->cache->put($key, $openUntil, $openSeconds);
        $this->cache->forget($this->failureCacheKey());

        if (is_int($previouslyOpenUntil) && $previouslyOpenUntil > $nowTimestamp) {
            return;
        }

        $this->recordCircuitEvent('circuit_open', $feature, $payload, $errorMessage);
    }

    private function closeCircuit(): void
    {
        $this->cache->forget($this->circuitOpenCacheKey());
        $this->cache->forget($this->failureCacheKey());
    }

    private function recordCircuitSkip(string $feature, array $payload): void
    {
        if (! $this->isCircuitBreakerEnabled()) {
            return;
        }

        $this->recordCircuitEvent('circuit_skip', $feature, $payload, 'AI circuit breaker open.');
    }

    private function recordCircuitEvent(string $action, string $feature, array $payload, ?string $errorMessage = null): void
    {
        $companyId = $this->currentCompanyId();

        if ($companyId === null) {
            return;
        }

        $this->recorder->record(
            companyId: $companyId,
            userId: $this->currentUserId(),
            feature: 'ai_circuit_breaker',
            requestPayload: [
                'action' => $action,
                'target_feature' => $feature,
                'payload_fingerprint' => $this->payloadFingerprint($payload),
                'threshold' => $this->circuitFailureThreshold(),
                'window_seconds' => $this->circuitBreakerWindowSeconds(),
                'open_seconds' => $this->circuitOpenSeconds(),
            ],
            responsePayload: null,
            latencyMs: null,
            status: AiEvent::STATUS_ERROR,
            errorMessage: $errorMessage,
        );
    }

    private function circuitFailureThreshold(): int
    {
        $threshold = (int) config('ai.circuit_breaker.failure_threshold', 5);

        return max(1, $threshold);
    }

    private function circuitBreakerWindowSeconds(): int
    {
        $window = (int) config('ai.circuit_breaker.window_seconds', 120);

        return max(1, $window);
    }

    private function circuitOpenSeconds(): int
    {
        $open = (int) config('ai.circuit_breaker.open_seconds', 300);

        return max(1, $open);
    }

    private function failureCacheKey(): string
    {
        $companyKey = $this->currentCompanyId() ?? 'global';

        return sprintf('ai:circuit:%s:failures', $companyKey);
    }

    private function circuitOpenCacheKey(): string
    {
        $companyKey = $this->currentCompanyId() ?? 'global';

        return sprintf('ai:circuit:%s:open', $companyKey);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{keys: array<int, int|string>, hash: string|null}
     */
    private function payloadFingerprint(array $payload): array
    {
        $encoded = json_encode($payload);

        return [
            'keys' => array_keys($payload),
            'hash' => $encoded === false ? null : sha1($encoded),
        ];
    }

    private function currentCompanyId(): ?int
    {
        $companyId = CompanyContext::get();

        return $companyId !== null ? (int) $companyId : null;
    }

    private function currentUserId(): ?int
    {
        $user = $this->request?->user();

        if ($user !== null) {
            $identifier = $user->getAuthIdentifier();

            return is_numeric($identifier) ? (int) $identifier : null;
        }

        $authUser = auth()->user();

        if ($authUser !== null) {
            $identifier = $authUser->getAuthIdentifier();

            return is_numeric($identifier) ? (int) $identifier : null;
        }

        return null;
    }
}
