<?php

return [
    'assets' => [
        'disk' => env('DIGITAL_TWIN_ASSET_DISK', env('FILESYSTEM_DISK', 's3')),
        'max_size_mb' => env('DIGITAL_TWIN_ASSET_MAX_SIZE_MB', 200),
        'allowed_mimes' => [
            'application/pdf',
            'application/octet-stream',
            'image/png',
            'image/jpeg',
            'application/vnd.ms-package.3dmanufacturing-3dmodel',
            'model/step',
            'model/stl',
            'application/vnd.ms-pki.stl',
        ],
        'allowed_extensions' => [
            'step',
            'stp',
            'stl',
            'iges',
            'igs',
            'dwg',
            'dxf',
            'pdf',
            'png',
            'jpg',
            'jpeg',
        ],
    ],
];
