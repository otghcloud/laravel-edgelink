# Data Logger Endpoint

Class: `DataLoggerEndpoint`

## Methods

- `query(array $payload = [], ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null)`

## Notes

The endpoint uses `/data/daq`.

## Response Shape Highlights

Query response normalizes to:

- `query`
- `records`
- `result`
