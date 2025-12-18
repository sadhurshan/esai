<?php

namespace App\Services\Ai;

use App\Exceptions\AiServiceUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class AiClient
{
    public function __construct(private readonly HttpFactory $http)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:string,message:string,data:array<string, mixed>|null,errors:array<string, mixed>}
     */
    public function forecast(array $payload): array
    {
        return $this->send('forecast', $payload, 'Forecast generated.');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:string,message:string,data:array<string, mixed>|null,errors:array<string, mixed>}
     */
    public function supplierRisk(array $payload): array
    {
        return $this->send('supplier-risk', $payload, 'Supplier risk assessed.');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:string,message:string,data:array<string, mixed>|null,errors:array<string, mixed>}
     */
    private function send(string $endpoint, array $payload, string $successMessage): array
    {
        if (! $this->isEnabled()) {
            return $this->disabledResponse();
        }

        try {
            $response = $this->pendingRequest()->post(ltrim($endpoint, '/'), $payload);
        } catch (ConnectionException $exception) {
            throw new AiServiceUnavailableException('AI service is unavailable.', 0, $exception);
        }

        return $this->formatResponse($response, $successMessage);
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
        $headers = [
            'X-AI-Secret' => $this->sharedSecret(),
        ];

        return array_filter($headers, static fn (?string $value): bool => $value !== null && $value !== '');
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

        return is_string($secret) ? $secret : null;
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
}
