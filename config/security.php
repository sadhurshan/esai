<?php

return [
    'scan_uploads' => (bool) env('SECURITY_SCAN_UPLOADS', true),
    'scan_driver' => env('SECURITY_SCAN_DRIVER', 'clamav'),
    'drivers' => [
        'clamav' => [
            'binary' => env('CLAMAV_BINARY', 'clamscan'),
            'arguments' => array_filter(array_map('trim', explode(',', (string) env('CLAMAV_ARGUMENTS', '--infected,--no-summary,--stdout')))),
            'timeout' => env('CLAMAV_TIMEOUT', 60),
        ],
    ],
];
