---
title: Usage Examples
---

# Usage Examples

## Basic Usage From Config

```php
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

$client = LaravelEdgelinkClient::fromConfig();
$client->login();

$tags = $client->getTags();
$version = $client->system()->version();
$aiChannel = $client->io()->ai(slot: 0, channel: 2);
```

## Runtime Connection Construction

```php
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

$client = LaravelEdgelinkClient::make(
    baseUrl: 'https://192.168.1.10',
    password: 'supersecretpassword',
    referer: 'https://192.168.1.10',
    verifyTls: false,
    timeoutSeconds: 10,
    responseMode: 'data',
    rawResponse: false,
    debugEnabled: false,
);

$client->login();
$tags = $client->tags()->list();
```

## Multiple RTUs

```php
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

$targets = [
    ['host' => 'https://192.168.1.10', 'password' => 'foo'],
    ['host' => 'https://192.168.1.11', 'password' => 'bar'],
];

foreach ($targets as $target) {
    $client = LaravelEdgelinkClient::make(
        baseUrl: $target['host'],
        password: $target['password'],
        referer: $target['host'],
        verifyTls: false,
    );

    $data = $client->tags()->list();
}
```

## Per-Call Response Overrides

```php
$default = $client->system()->version();
$raw = $client->system()->version(raw: true);
$envelope = $client->system()->version(responseMode: 'envelope');
$withDebug = $client->system()->version(responseMode: 'envelope', debug: true);
```

## Canonical Endpoint Patterns

IO canonical methods:

```php
$read = $client->io()->read(type: 'ai', slot: 0, channel: 2);
$write = $client->io()->write(type: 'do', slot: 0, channel: 1, value: true);
```

Tags canonical methods:

```php
$all = $client->tags()->list();
$one = $client->tags()->read('Slot1:DI_5_SD_LockStatus');
$field = $client->tags()->read('Slot1:DI_5_SD_LockStatus', 'value');
$set = $client->tags()->value('Slot1:DO_0', 1);
```

Next:

- [Endpoint Reference](endpoints/index.md)
