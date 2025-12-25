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
    'scraper' => [
        'poll_interval_seconds' => (int) env('AI_SCRAPER_POLL_INTERVAL_SECONDS', 30),
        'max_duration_seconds' => (int) env('AI_SCRAPER_MAX_DURATION_SECONDS', 900),
    ],
    'retention' => [
        'mode' => env('AI_RETENTION_MODE', 'delete'),
        'events_days' => (int) env('AI_RETENTION_EVENTS_DAYS', 90),
        'chat_messages_days' => (int) env('AI_RETENTION_CHAT_MESSAGES_DAYS', 90),
        'archive_disk' => env('AI_RETENTION_ARCHIVE_DISK', 'local'),
        'archive_directory' => env('AI_RETENTION_ARCHIVE_DIRECTORY', 'ai/archives'),
    ],
];
