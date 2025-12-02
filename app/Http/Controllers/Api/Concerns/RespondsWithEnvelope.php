<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

trait RespondsWithEnvelope
{
    protected function ok(mixed $data = null, ?string $message = null, ?array $meta = null): JsonResponse
    {
        [$dataMeta, $envelopeMeta] = $this->splitMeta($meta);

        if (is_array($data) && $dataMeta !== null && ! array_key_exists('meta', $data)) {
            $data['meta'] = $dataMeta;
        }

        $payloadMeta = $this->buildResponseMeta($envelopeMeta);

        $payload = [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'meta' => $payloadMeta,
        ];

        return response()->json($payload)->header('X-Request-Id', $payload['meta']['request_id']);
    }

    protected function fail(string $message, int $code = 400, ?array $errors = null): JsonResponse
    {
        $payload = [
            'status' => 'error',
            'message' => $message,
            'data' => null,
            'meta' => $this->buildResponseMeta(),
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $code)->header('X-Request-Id', $payload['meta']['request_id']);
    }

    /**
     * @return array{0: array<string, mixed>|null, 1: array<string, mixed>|null}
     */
    private function splitMeta(?array $meta): array
    {
        if ($meta === null) {
            return [null, null];
        }

        if (array_key_exists('data', $meta) || array_key_exists('envelope', $meta)) {
            $envelope = $meta['envelope'] ?? [];
            $passthrough = array_diff_key($meta, array_flip(['data', 'envelope']));

            if ($passthrough !== []) {
                $envelope = array_merge($envelope, $passthrough);
            }

            return [$meta['data'] ?? null, $envelope];
        }

        return [null, $meta];
    }

    private function buildResponseMeta(?array $overrides = null): array
    {
        $request = request();
        $currentId = $request?->headers->get('X-Request-Id') ?? $request?->attributes->get('request_id');
        $requestId = (string) ($currentId ?: Str::uuid());

        if ($request !== null) {
            $request->attributes->set('request_id', $requestId);
        }

        $meta = ['request_id' => $requestId];

        if ($overrides !== null) {
            $meta = array_merge($meta, $overrides);
        }

        return $meta;
    }
}
