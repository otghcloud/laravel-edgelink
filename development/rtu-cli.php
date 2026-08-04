#!/usr/bin/env php
<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Facade;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

require __DIR__.'/../vendor/autoload.php';

bootstrapFacadeContainer();

/**
 * Lightweight package-local CLI for local development
 */
$args = $argv;
array_shift($args);

// Optional local defaults for development convenience.
// Precedence is: CLI option > environment variable > development/config.php > internal defaults.
$internalDefaults = [
    'base-url' => '',
    'password' => '',
    'referer' => null,
    'verify-tls' => false,
    'timeout' => 10,
    'response-mode' => 'data',
    'raw' => null,
    'debug' => null,
];

$scriptConfigPath = __DIR__.'/config.php';
$scriptDefaults = [];

if (is_file($scriptConfigPath)) {
    $loaded = require $scriptConfigPath;

    if (! is_array($loaded)) {
        fwrite(STDERR, "Invalid development/config.php: expected returned array.\n");
        exit(2);
    }

    $scriptDefaults = $loaded;
}

$developmentDefaults = array_replace($internalDefaults, $scriptDefaults);

$command = $args[0] ?? null;
if ($command === null || in_array($command, ['-h', '--help'], true)) {
    fwrite(STDOUT, <<<'TXT'
Usage:
    php development/rtu-cli.php <command> [--key=value]

Connection options (required unless env provided):
  --base-url=...           or EDGELINK_BASE_URL
  --password=...           or EDGELINK_PASSWORD
  --referer=...            optional, defaults to base-url
  --verify-tls=true|false  optional, default false
  --timeout=10             optional seconds

Output options:
  --response-mode=data|envelope
  --raw=true|false
  --debug=true|false

Commands:
  version
  tags:list
  tags:read --name=<tag>
  tags:value --name=<tag> --value=<value>
  io:read --type=ai|ao|di|do --slot=<n> [--channel=<n>]
  io:write --type=ai|ao|di|do --slot=<n> --channel=<n> --value=<value>
  probe

Examples:
    php development/rtu-cli.php version --base-url=https://192.168.1.10 --password=secret
    php development/rtu-cli.php tags:list --base-url=https://192.168.1.10 --password=secret --response-mode=envelope
    php development/rtu-cli.php io:read --type=ai --slot=0 --channel=0 --base-url=https://192.168.1.10 --password=secret
TXT
    );
    exit(0);
}

$options = parseOptions(array_slice($args, 1));

$baseUrl = (string) resolveOption($options, 'base-url', 'EDGELINK_BASE_URL', $developmentDefaults);
$password = (string) resolveOption($options, 'password', 'EDGELINK_PASSWORD', $developmentDefaults);
$referer = (string) (resolveOption($options, 'referer', 'EDGELINK_REFERER', $developmentDefaults) ?: $baseUrl);
$verifyTls = toBool(resolveOption($options, 'verify-tls', 'EDGELINK_VERIFY_TLS', $developmentDefaults));
$timeout = (int) resolveOption($options, 'timeout', 'EDGELINK_TIMEOUT_SECONDS', $developmentDefaults);
$responseMode = (string) resolveOption($options, 'response-mode', 'EDGELINK_RESPONSE_MODE', $developmentDefaults);
$raw = optionOrNullBool($options, 'raw', envKey: 'EDGELINK_RAW_RESPONSE', defaults: $developmentDefaults);
$debug = optionOrNullBool($options, 'debug', envKey: 'EDGELINK_DEBUG_ENABLED', defaults: $developmentDefaults);

if ($baseUrl === '' || $password === '') {
    fwrite(STDERR, "Missing required connection options: --base-url and --password (or EDGELINK_BASE_URL/EDGELINK_PASSWORD).\n");
    exit(2);
}

$client = LaravelEdgelinkClient::make(
    baseUrl: rtrim($baseUrl, '/'),
    password: $password,
    referer: $referer,
    verifyTls: $verifyTls,
    timeoutSeconds: $timeout,
    responseMode: $responseMode,
    rawResponse: false,
    debugEnabled: false,
);

try {
    $result = match ($command) {
        'version' => $client->system()->version(raw: $raw, responseMode: $responseMode, debug: $debug),
        'tags:list' => $client->tags()->list(raw: $raw, responseMode: $responseMode, debug: $debug),
        'tags:read' => $client->tags()->read(
            tagName: requiredString($options, 'name'),
            field: $options['field'] ?? null,
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
        ),
        'tags:value' => $client->tags()->value(
            tagName: requiredString($options, 'name'),
            value: requiredString($options, 'value'),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
        ),
        'io:read' => $client->io()->read(
            type: requiredString($options, 'type'),
            slot: (int) requiredString($options, 'slot'),
            channel: isset($options['channel']) ? (int) $options['channel'] : null,
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
        ),
        'io:write' => $client->io()->write(
            type: requiredString($options, 'type'),
            slot: (int) requiredString($options, 'slot'),
            channel: (int) requiredString($options, 'channel'),
            value: requiredString($options, 'value'),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
        ),
        'probe' => runProbe($client, $responseMode, $raw, $debug),
        default => throw new InvalidArgumentException('Unsupported command: '.$command),
    };

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
} catch (Throwable $e) {
    $error = [
        'ok' => false,
        'error' => [
            'message' => $e->getMessage(),
            'type' => get_class($e),
        ],
    ];

    echo json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}

/**
 * @param  array<int, string>  $args
 * @return array<string, string>
 */
function parseOptions(array $args): array
{
    $parsed = [];

    foreach ($args as $arg) {
        if (! str_starts_with($arg, '--')) {
            continue;
        }

        $trimmed = substr($arg, 2);
        $parts = explode('=', $trimmed, 2);
        $key = $parts[0];
        $value = $parts[1] ?? 'true';
        $parsed[$key] = $value;
    }

    return $parsed;
}

/**
 * @param  array<string, string>  $options
 */
function requiredString(array $options, string $key): string
{
    if (! isset($options[$key]) || trim($options[$key]) === '') {
        throw new InvalidArgumentException('Missing required option: --'.$key);
    }

    return $options[$key];
}

/**
 * @param  array<string, string>  $options
 */
function optionOrNullBool(array $options, string $key, ?string $envKey = null, array $defaults = []): ?bool
{
    $value = resolveOption($options, $key, $envKey, $defaults, required: false);

    if ($value === null || $value === '') {
        return null;
    }

    return toBool($value);
}

/**
 * @param  array<string, string>  $options
 * @param  array<string, mixed>  $defaults
 */
function resolveOption(array $options, string $key, ?string $envKey, array $defaults, bool $required = false): mixed
{
    if (array_key_exists($key, $options)) {
        return $options[$key];
    }

    if ($envKey !== null) {
        $envValue = getenv($envKey);
        if ($envValue !== false && trim((string) $envValue) !== '') {
            return $envValue;
        }
    }

    if (array_key_exists($key, $defaults)) {
        return $defaults[$key];
    }

    if ($required) {
        throw new InvalidArgumentException('Missing required option: --'.$key);
    }

    return null;
}

function toBool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    $normalized = strtolower(trim((string) $value));

    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return false;
}

function runProbe(LaravelEdgelinkClient $client, string $responseMode, ?bool $raw, ?bool $debug): array
{
    $checks = [];

    $run = static function (string $name, callable $fn) use (&$checks): void {
        try {
            $checks[] = [
                'name' => $name,
                'ok' => true,
                'data' => $fn(),
                'error' => null,
            ];
        } catch (Throwable $e) {
            $checks[] = [
                'name' => $name,
                'ok' => false,
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    };

    $run('system.version', fn () => $client->system()->version(raw: $raw, responseMode: $responseMode, debug: $debug));
    $run('tags.list', function () use ($client, $raw, $responseMode, $debug): mixed {
        $tags = $client->tags()->list(raw: $raw, responseMode: $responseMode, debug: $debug);

        if (is_array($tags) && array_is_list($tags)) {
            return [
                'count' => count($tags),
                'sample' => array_slice($tags, 0, 5),
            ];
        }

        return $tags;
    });
    $run('io.read.ai.slot0.ch0', fn () => $client->io()->read('ai', 0, 0, raw: $raw, responseMode: $responseMode, debug: $debug));
    $run('io.read.di.slot0.ch0', fn () => $client->io()->read('di', 0, 0, raw: $raw, responseMode: $responseMode, debug: $debug));
    $run('io.read.do.slot0.ch0', fn () => $client->io()->read('do', 0, 0, raw: $raw, responseMode: $responseMode, debug: $debug));

    return [
        'target' => $client->getConfig('base_url'),
        'checks' => $checks,
    ];
}

function bootstrapFacadeContainer(): void
{
    if (Facade::getFacadeApplication() instanceof Container) {
        return;
    }

    $app = new Container;
    $app->instance('app', $app);
    $app->singleton('http', static fn (): HttpFactory => new HttpFactory);

    Facade::setFacadeApplication($app);
}
