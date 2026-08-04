[<img src="https://otgh-static-assets.s3.otgh.cloud/branding/logos/otgh_cloud_2024.png" alt="OTGH Cloud" width="200px" />](https://github.com/otghcloud/laravel-edgelink)

# Laravel Edgelink Client

A full featured Laravel client for Advantech's Edgelink compatible devices (i.e ADAM-3600 RTU).

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

This creates `config/edgelink.php`, allowing you to define default credentials.

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

```php
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

$client = LaravelEdgelinkClient::fromConfig();
$client->login();
$tags = $client->getTags();
$oneTag = $client->getTag('Slot1:DI_5_SD_LockStatus');

$version = $client->system()->version();
$aiChannel = $client->io()->ai(slot: 0, channel: 2);
```

### Runtime Connections (No Config Required)

You can create clients directly with runtime connection details when you need to talk to many RTUs dynamically.

```php
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

$client = LaravelEdgelinkClient::make(
	baseUrl: 'https://192.168.1.10',
	password: 'rtu-password',
	referer: 'https://192.168.1.10',
	verifyTls: false,
	timeoutSeconds: 10,
);

$client->login();
$tags = $client->getTags();
```

You can also pass a runtime array into `fromConfig()`.

```php
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

$client = LaravelEdgelinkClient::fromConfig([
	'base_url' => 'https://10.5.1.60',
	'password' => 'rtu-password',
	'referer' => 'https://10.5.1.60',
	'verify_tls' => false,
	'timeout_seconds' => 10,
]);
```

Example with multiple RTUs in one job/request:

```php
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

$rtus = [
	['host' => '10.5.1.60', 'password' => 'foo'],
	['host' => '10.5.1.61', 'password' => 'bar'],
];

foreach ($rtus as $rtu) {
	$baseUrl = 'https://'.$rtu['host'];

	$client = LaravelEdgelinkClient::make(
		baseUrl: $baseUrl,
		password: $rtu['password'],
		referer: $baseUrl,
		verifyTls: false,
		timeoutSeconds: 10,
	);

	$tag = $client->getTag('Slot1:DI_5_SD_LockStatus');
	// Handle each RTU result here.
}
```

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.