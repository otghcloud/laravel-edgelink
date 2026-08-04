# Network Endpoint

Class: `NetworkEndpoint`

## Canonical Methods

- `read(string $segment, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null)`
- `write(string $segment, string $path, array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null, ?string $target = null, string $method = 'PUT')`

Supported segment values:

- `cellular_info`
- `lan`
- `wlan`
- `cellular`
- `cellular_status`
- `gps`
- `remoteit`

## Convenience Methods

Read wrappers:

- `cellularInfo`
- `lan`
- `wlan`
- `cellular`
- `cellularStatus`
- `gps`
- `remoteIt`

Write wrappers:

- `updateLan(string $id, array $payload)`
- `updateWlan(string $id, array $payload)`
- `updateCellular(array $payload)`
- `patchGps(array $payload)`

## Response Shape Highlights

Read response normalizes to:

- `segment`
- `data`

Write response normalizes to:

- `segment`
- `target`
- `path`
- `payload`
- `result`
