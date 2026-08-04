# Firmware Endpoint

Class: `FirmwareEndpoint`

## Methods

- `verifyFile(array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null)`
- `upload(array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null)`
- `update(array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null)`
- `recoverDefaultImage(array $payload = [], ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null)`

## Response Shape Highlights

All firmware actions normalize to:

- `action`
- `path`
- `payload`
- `result`
