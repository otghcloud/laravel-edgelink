[<img src="https://otgh-static-assets.s3.otgh.cloud/branding/logos/otgh_cloud_2024.png" alt="OTGH Cloud" width="200px" />](https://github.com/otghcloud/laravel-edgelink)

# Laravel Edgelink Client

A full featured Laravel client for Advantech's Edgelink compatible devices (i.e ADAM-3600 RTU), implementing the RESTFul API specification [published by Advantech here](https://www.advantech.com/en-us/support/details/software%20api?id=1-1KPLJQG).

## Features

- **auth()**: login/logout
- **tags()**: tags list, single-tag lookup, tag value updates
- **system()**: control/version/update info/web settings/device info
- **io()**: AI/AO/DI/DO slot/channel reads and writes
- **dataLogger()**: daq query wrapper
- **firmware()**: verify/upload/update/recovery wrappers
- **logs()**: log_create/log_message wrappers
- **network()**: lan/wlan/cellular/gps/remoteit wrappers

## Installation

You can install the package via composer:

```bash
composer require otghcloud/laravel-edgelink
```

## Configuration

If you wish to specify a default connection, you can publish the [config file](config/edgelink.php) by running the below:

```bash
php artisan vendor:publish --tag="laravel-edgelink-config"
```

This creates `config/edgelink.php`, allowing you to define default connection details.

These can of course be changed at runtime (see further below).

```php
return [
    'base_url' => env('EDGELINK_BASE_URL'),
    'password' => env('EDGELINK_PASSWORD'),
    'referer' => env('EDGELINK_REFERER'),
    'verify_tls' => env('EDGELINK_VERIFY_TLS', false),
    'timeout_seconds' => env('EDGELINK_TIMEOUT_SECONDS', 10),
];
```

## Usage

By default, the package will use any values from the above configuration file if published.


```php
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

$client = LaravelEdgelinkClient::fromConfig();
$client->login();
$tags = $client->getTags();
$oneTag = $client->getTag('Slot1:DI_5_SD_LockStatus');

$version = $client->system()->version();
$aiChannel = $client->io()->ai(slot: 0, channel: 2);
```

You can also pass an array into `fromConfig()` to override any previously defined values.

```php
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

$client = LaravelEdgelinkClient::fromConfig([
	'base_url' => 'https://192.168.1.10',
	'password' => 'supersecretpassword',
	'referer' => 'https://192.168.1.10',
	'verify_tls' => false,
	'timeout_seconds' => 10,
]);
```

### Runtime Connections

Alternatively, you can create clients directly with on-the-fly connection details when you need to talk to many RTUs dynamically.

```php
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

$client = LaravelEdgelinkClient::make(
	baseUrl: 'https://192.168.1.10',
	password: 'supersecretpassword',
	referer: 'https://192.168.1.10',
	verifyTls: false,
	timeoutSeconds: 10,
);

$client->login();
$tags = $client->getTags();
dump($tags);
```

Example with multiple RTUs in one job/request:

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
		timeoutSeconds: 10,
	);

	$tags = $client->getTags();
	dump($tags);

}
```

## Compatbility / Requirements

The minimum environment requirements are as below:

- Laravel 13+
- PHP 8.3+

Our testing has been done on ADAM-3600 units from firmware versions 2.8.0 to 2.8.4.6.

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.