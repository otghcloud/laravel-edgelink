---
title: IO Endpoint
---

# IO Endpoint

Class: `IoEndpoint`

## Canonical Methods

- `read(string $type, int $slot, ?int $channel = null, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null)`
- `write(string $type, int $slot, int $channel, int|float|string|bool $value, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null)`

Supported `type` values:

- `ai`
- `ao`
- `di`
- `do`

## Convenience Methods

Read wrappers:

- `ai`, `ao`, `di`, `do`

Write wrappers:

- `setAi`, `setAo`, `setDi`, `setDo`

## Response Shape Highlights

Single channel read normalizes to:

- `type`
- `slot`
- `channel`
- `value`

Slot-level read normalizes to:

- `type`
- `slot`
- `channels` list

Write response normalizes to:

- `type`
- `slot`
- `channel`
- `written_value`
- `result`

## Examples

```php
$ai = $client->io()->read(type: 'ai', slot: 0, channel: 2);
$doWrite = $client->io()->write(type: 'do', slot: 0, channel: 1, value: true);

$legacyRead = $client->io()->ai(slot: 0, channel: 2);
$legacyWrite = $client->io()->setDo(slot: 0, channel: 1, value: true);
```
