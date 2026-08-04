# System Endpoint

Class: `SystemEndpoint`

## Methods

- `version(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null)`
- `updateInfo(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null)`
- `restart(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null)`
- `control(`
  `array $payload,`
  `?bool $raw = null,`
  `?string $responseMode = null,`
  `?bool $debug = null`
  `)`
- `calibration(`
  `array $payload,`
  `?bool $raw = null,`
  `?string $responseMode = null,`
  `?bool $debug = null`
  `)`
- `webSettings(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null)`
- `updateWebSettings(`
  `array $payload,`
  `?bool $raw = null,`
  `?string $responseMode = null,`
  `?bool $debug = null`
  `)`
- `deviceInfo(`
  `int $slot = 0,`
  `?bool $raw = null,`
  `?string $responseMode = null,`
  `?bool $debug = null`
  `)`
- `updateDeviceInfo(`
  `int $slot,`
  `array $payload,`
  `?bool $raw = null,`
  `?string $responseMode = null,`
  `?bool $debug = null`
  `)`

## Behavior Notes

`version` handles both modern and legacy firmware endpoints:

- Primary: `/sys/version`
- Legacy fallback on HTTP 400 or 500: `/xml/version.xml`

Normalized version fields:

- `version`
- `released`
- `released_at`

`restart` supports token handshake behavior when newer firmware returns a token.

## Example

```php
$version = $client->system()->version();
$info = $client->system()->updateInfo();
$restart = $client->system()->restart(responseMode: 'envelope');
```
