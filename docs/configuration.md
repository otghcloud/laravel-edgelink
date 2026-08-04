---
title: Configuration
---

# Configuration

The package reads config from `config/edgelink.php`.

## Connection Settings

- `base_url`
- `password`
- `referer`
- `verify_tls`
- `timeout_seconds`

## Response Behavior Settings

- `response_mode`: `data` or `envelope`
- `raw_response`: `true` or `false`
- `debug_enabled`: include debug payload in envelope mode
- `debug_request_headers`
- `debug_request_body`
- `debug_response_headers`
- `debug_response_body`

## Default Config Example

```php
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
```

## Response Modes

`data` mode returns only normalized payload data.

`envelope` mode returns:

```php
[
    'ok' => true,
    'data' => [...],
    'error' => null,
    'meta' => [...],
    'debug' => null,
]
```

## Raw Response Override

If `raw_response` is true, endpoint methods return raw upstream payloads.

Per-call override is available via endpoint arguments:

- `raw`
- `responseMode`
- `debug`

See [Usage Examples](usage-examples.md) for examples.
