<?php

return [
    'base_url' => env('EDGELINK_BASE_URL'),
    'password' => env('EDGELINK_PASSWORD'),
    'referer' => env('EDGELINK_REFERER', env('EDGELINK_BASE_URL')),
    'verify_tls' => env('EDGELINK_VERIFY_TLS', false),
    'timeout_seconds' => env('EDGELINK_TIMEOUT_SECONDS', 10),
    'response_mode' => env('EDGELINK_RESPONSE_MODE', 'data'),
    'raw_response' => env('EDGELINK_RAW_RESPONSE', false),
    'debug_enabled' => env('EDGELINK_DEBUG_ENABLED', false),
    'debug_request_headers' => env('EDGELINK_DEBUG_REQUEST_HEADERS', true),
    'debug_request_body' => env('EDGELINK_DEBUG_REQUEST_BODY', true),
    'debug_response_headers' => env('EDGELINK_DEBUG_RESPONSE_HEADERS', true),
    'debug_response_body' => env('EDGELINK_DEBUG_RESPONSE_BODY', true),
];
