---
title: Tags Endpoint
---

# Tags Endpoint

Class: `TagsEndpoint`

## Canonical Methods

- `list(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null)`
- `read(string $tagName, ?string $field = null, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null)`
- `write(string $path, array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null)`
- `value(string $tagName, int|float|string|bool $value, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null)`

## Alias Methods

- `all` maps to `list`
- `one` maps to `read(tagName)`
- `update` maps to `write`
- `updateValue` maps to `value`
- `getField` maps to `read(tagName, field)`

Specialized helper:

- `updateDoValue(int $slot, int $channel, int|float|string|bool $value, ... )`

## Response Shape Highlights

Collection reads normalize into a stable tag list and ensure a `name` key is present.

Write operations normalize into:

- `path`
- `tag_name`
- `field`
- `payload`
- `result`

## Examples

```php
$all = $client->tags()->list();
$tag = $client->tags()->read('Slot1:DI_5_SD_LockStatus');
$field = $client->tags()->read('Slot1:DI_5_SD_LockStatus', 'value');
$set = $client->tags()->value('Slot1:DO_0', 1);
```
