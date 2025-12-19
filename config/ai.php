<?php

return [
    'enabled' => (bool) env('AI_ENABLED', false),
    'base_url' => env('AI_BASE_URL', env('AI_MICROSERVICE_URL', 'http://localhost:8001')),
    'timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', env('AI_MICROSERVICE_TIMEOUT', 15)),
    'shared_secret' => env('AI_SHARED_SECRET'),
    'circuit_breaker' => [
        'enabled' => (bool) env('AI_CIRCUIT_BREAKER_ENABLED', true),
        'failure_threshold' => (int) env('AI_CIRCUIT_FAILURE_THRESHOLD', 5),
        'window_seconds' => (int) env('AI_CIRCUIT_WINDOW_SECONDS', 120),
        'open_seconds' => (int) env('AI_CIRCUIT_OPEN_SECONDS', 300),
    ],
    'rate_limit' => [
        'enabled' => (bool) env('AI_RATE_LIMIT_ENABLED', true),
        'requests_per_minute' => (int) env('AI_RATE_LIMIT_PER_MINUTE', 30),
        'window_seconds' => (int) env('AI_RATE_LIMIT_WINDOW_SECONDS', 60),
    ],
];
