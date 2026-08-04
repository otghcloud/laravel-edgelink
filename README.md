[<img src="https://otgh-static-assets.s3.otgh.cloud/branding/logos/otgh_cloud_2024.png" alt="OTGH Cloud" width="200px" />](https://github.com/otghcloud/laravel-edgelink)

# Laravel Edgelink Client

A full featured Laravel client for Advantech Edgelink compatible devices
(for example ADAM-3600 RTU), implementing the REST API specification
published by Advantech.

The package now exposes a canonical endpoint API surface only.
Legacy alias helper methods for tags and IO have been removed.

## Installation

```bash
composer require otghcloud/laravel-edgelink
```

## Basic Usage

```php
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

$client = LaravelEdgelinkClient::fromConfig();
$client->login();

$version = $client->system()->version();
$tags = $client->tags()->list();
```

## Documentation

Full documentation is available in the docs folder:

- [Documentation Home](docs/index.md)
- [Installation](docs/installation.md)
- [Configuration](docs/configuration.md)
- [Usage Examples](docs/usage-examples.md)
- [Endpoint Reference](docs/endpoints/index.md)
- [Compatibility Matrix](docs/compatibility.md)
- [Error Handling](docs/error-handling.md)

## Compatibility

- Laravel 13+
- PHP 8.3+

Tested on ADAM-3600 firmware versions 2.8.0 to 2.8.4.6.

## Development

Developer-specific guidance is available in [DEVELOPMENT.md](DEVELOPMENT.md).

## Quality Gates

- PHPUnit suite runs across PHP 8.3, 8.4, and 8.5.
- PHPStan static analysis is enforced in CI.
- Coverage is generated in CI and must meet the minimum threshold.

## Release Notes

- [Changelog](CHANGELOG.md)
- [Security Policy](.github/SECURITY.md)

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md).
