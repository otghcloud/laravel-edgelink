# Error Handling

## Exception Hierarchy

- `EdgelinkException`
- `AuthenticationException`
- `ConfigurationException`
- `RequestException`
- `ResponseModeException`
- `ResponseTransformationException`
- `SessionException`

## Common Recovery Patterns

### Authentication failures

- Verify `base_url`, `password`, and network reachability.
- Re-run login and ensure session cookies/IDs are returned.

### Request failures

- Catch `RequestException` and inspect:
  - `method()`
  - `path()`
  - `statusCode()`
  - `responseBody()`

### Response transformation failures

- Catch `ResponseTransformationException` when device payloads are missing expected fields.
- Log raw payloads during triage using envelope debug mode.

## Debug Trace Usage

Enable debug mode to include request/response exchanges:

```php
$client = LaravelEdgelinkClient::make(
    baseUrl: 'https://rtu.local',
    password: 'secret',
    responseMode: 'envelope',
    debugEnabled: true,
);

$result = $client->system()->version();
```

Use debug field toggles to limit sensitive output in logs.
