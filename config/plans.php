<?php

return [
    'features' => [
        'ai_training_enabled' => [
            'plan_codes' => array_values(array_unique(array_filter(array_map(
                static fn (string $code): string => strtolower(trim($code)),
                explode(',', (string) env('PLAN_AI_TRAINING_CODES', 'enterprise')),
            ), static fn (string $code): bool => $code !== ''))),
            'default' => false,
        ],
    ],
];
