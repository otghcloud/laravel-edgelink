# Laravel Edgelink Documentation

Welcome to the documentation for the Laravel Edgelink client package.

## What Is Included

- Installation and setup guidance
- Configuration and response-mode behavior
- Practical usage examples
- Endpoint-by-endpoint method reference

## Quick Start

Install the package:

```bash
composer require otghcloud/laravel-edgelink
```

Basic usage:

```php
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

$client = LaravelEdgelinkClient::fromConfig();
$client->login();

$version = $client->system()->version();
$tags = $client->tags()->list();
```

## Documentation Map

- [Installation](installation.md)
- [Configuration](configuration.md)
- [Usage Examples](usage-examples.md)
- [Endpoint Reference](endpoints/index.md)
- [Compatibility Matrix](compatibility.md)
- [Error Handling](error-handling.md)

## Related Guides

- [Development Guide](https://github.com/otghcloud/laravel-edgelink/blob/main/DEVELOPMENT.md)
- [Project README](https://github.com/otghcloud/laravel-edgelink/blob/main/README.md)
- [Changelog](changelog.md)
- [Security Policy](security.md)
