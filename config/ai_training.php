<?php

return [
    'training_enabled' => (bool) env('AI_TRAINING_ENABLED', true),
    'default_forecast_window_months' => (int) env('AI_TRAINING_DEFAULT_FORECAST_WINDOW_MONTHS', 6),
    'max_training_runtime_minutes' => (int) env('AI_TRAINING_MAX_RUNTIME_MINUTES', 60),
    'allowed_file_types' => array_values(array_filter(array_map(
        static fn ($value) => trim((string) $value),
        explode(',', (string) env('AI_TRAINING_ALLOWED_FILE_TYPES', 'csv,jsonl,zip')),
    ))),
];
