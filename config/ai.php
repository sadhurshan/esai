<?php

return [
    'enabled' => (bool) env('AI_ENABLED', false),
    'base_url' => env('AI_BASE_URL', env('AI_MICROSERVICE_URL', 'http://localhost:8001')),
    'timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', env('AI_MICROSERVICE_TIMEOUT', 15)),
    'shared_secret' => env('AI_SHARED_SECRET'),
];
