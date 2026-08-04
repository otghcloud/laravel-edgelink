<?php

return [
    'base_url' => env('EDGELINK_BASE_URL'),
    'password' => env('EDGELINK_PASSWORD'),
    'referer' => env('EDGELINK_REFERER', env('EDGELINK_BASE_URL')),
    'verify_tls' => env('EDGELINK_VERIFY_TLS', false),
    'timeout_seconds' => env('EDGELINK_TIMEOUT_SECONDS', 10),
];
