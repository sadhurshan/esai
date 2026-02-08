<?php

use Illuminate\Http\Request;

return [
    'enabled' => (bool) env('CLOUDFLARE_ENABLED', false),
    'trusted_proxies' => env(
        'CLOUDFLARE_TRUSTED_PROXIES',
        env('CLOUDFLARE_ENABLED', false) ? 'REMOTE_ADDR' : null
    ),
    'trusted_headers' => Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_PREFIX
        | Request::HEADER_X_FORWARDED_AWS_ELB,
    'connecting_ip_header' => env('CLOUDFLARE_CONNECTING_IP_HEADER', 'CF-Connecting-IP'),
    'visitor_header' => env('CLOUDFLARE_VISITOR_HEADER', 'CF-Visitor'),
];
