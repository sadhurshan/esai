<?php

return [
    'permissions' => [
        // TODO: Replace with chat-specific permission list from the spec when available.
        'ai.workflows.run',
    ],
    'history_limit' => (int) env('AI_CHAT_HISTORY_LIMIT', 30),
    'streaming' => [
        'enabled' => (bool) env('AI_CHAT_STREAMING_ENABLED', true),
        'token_ttl' => (int) env('AI_CHAT_STREAM_TOKEN_TTL', 180),
    ],
    'memory' => [
        'enabled' => (bool) env('AI_CHAT_MEMORY_SUMMARY_ENABLED', true),
        'summary_max_chars' => (int) env('AI_CHAT_SUMMARY_MAX_CHARS', 1800),
    ],
    'tooling' => [
        'max_calls_per_request' => (int) env('AI_CHAT_TOOL_MAX_CALLS', 3),
        'max_rounds_per_message' => (int) env('AI_CHAT_TOOL_MAX_ROUNDS', 3),
    ],
    'logs' => [
        'message_preview_length' => (int) env('AI_CHAT_LOG_PREVIEW_CHARS', 240),
    ],
];
