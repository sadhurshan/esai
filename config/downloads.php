<?php

return [
    'disk' => env('DOWNLOADS_DISK', 'downloads'),
    'ttl_days' => env('DOWNLOADS_TTL_DAYS', 7),
    'max_attempts' => env('DOWNLOADS_MAX_ATTEMPTS', 3),
];
