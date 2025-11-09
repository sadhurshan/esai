<?php

return [
    'disk' => env('DOCUMENTS_DISK', env('FILESYSTEM_DISK', 's3')),
    'max_size_mb' => env('DOCUMENTS_MAX_SIZE_MB', 50),
    'allowed_extensions' => [
        'step',
        'stp',
        'iges',
        'igs',
        'dwg',
        'dxf',
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'csv',
        'png',
        'jpg',
        'jpeg',
        'tif',
        'tiff',
    ],
    'allowed_visibilities' => ['private', 'company', 'public'],
    'default_visibility' => env('DOCUMENTS_DEFAULT_VISIBILITY', 'company'),
];
