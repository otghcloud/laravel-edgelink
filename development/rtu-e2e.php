#!/usr/bin/env php
<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Facade;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

require __DIR__.'/../vendor/autoload.php';

bootstrapFacadeContainer();

$args = $argv;
array_shift($args);
$options = parseOptions($args);

if (isset($options['help']) || isset($options['h'])) {
    printUsage();
    exit(0);
}

$defaultOptions = [
    'profiles' => 'all',
    'allow-write' => 'false',
    'fail-on-failures' => 'false',
    'report-dir' => __DIR__.'/reports',
    'output-prefix' => 'rtu-e2e',
    'response-mode' => 'data',
    'debug' => 'false',
];

$resolvedOptions = array_replace($defaultOptions, $options);

$profilesPath = __DIR__.'/profiles.php';
if (! is_file($profilesPath)) {
    fwrite(STDERR, "Missing development/profiles.php. Copy development/profiles.php.dist and configure your RTUs.\n");
    exit(2);
}

$loadedProfiles = require $profilesPath;
if (! is_array($loadedProfiles)) {
    fwrite(STDERR, "Invalid development/profiles.php: expected a returned array.\n");
    exit(2);
}

$profileNames = resolveSelectedProfiles((string) $resolvedOptions['profiles'], $loadedProfiles);
if ($profileNames === []) {
    fwrite(STDERR, "No profiles selected.\n");
    exit(2);
}

$allowWrite = toBool($resolvedOptions['allow-write']);
$failOnFailures = toBool($resolvedOptions['fail-on-failures']);
$responseMode = normalizeResponseMode((string) $resolvedOptions['response-mode']);
$debug = toBool($resolvedOptions['debug']);

$reportDir = (string) $resolvedOptions['report-dir'];
if ($reportDir === '') {
    fwrite(STDERR, "Option --report-dir must not be empty.\n");
    exit(2);
}

if (! is_dir($reportDir) && ! mkdir($reportDir, 0775, true) && ! is_dir($reportDir)) {
    fwrite(STDERR, "Unable to create report directory: {$reportDir}\n");
    exit(2);
}

$runId = date('Ymd-His');
$profileReports = [];

foreach ($profileNames as $profileName) {
    $profileData = $loadedProfiles[$profileName] ?? null;

    if (! is_array($profileData)) {
        $profileReports[] = [
            'profile' => $profileName,
            'target' => null,
            'summary' => [
                'pass' => 0,
                'fail' => 1,
                'skip' => 0,
                'total' => 1,
            ],
            'results' => [[
                'name' => 'profile.load',
                'category' => 'bootstrap',
                'mutating' => false,
                'status' => 'fail',
                'duration_ms' => 0,
                'reason' => null,
                'details' => null,
                'error' => [
                    'type' => 'RuntimeException',
                    'message' => 'Profile is not an array.',
                ],
            ]],
        ];

        continue;
    }

    $profileReports[] = runProfile(
        profileName: $profileName,
        profileData: $profileData,
        allowWrite: $allowWrite,
        responseMode: $responseMode,
        debug: $debug,
    );
}

$summary = summarizeProfiles($profileReports);

$report = [
    'run_id' => $runId,
    'generated_at' => gmdate('c'),
    'options' => [
        'profiles' => $profileNames,
        'allow_write' => $allowWrite,
        'fail_on_failures' => $failOnFailures,
        'response_mode' => $responseMode,
        'debug' => $debug,
        'report_dir' => $reportDir,
    ],
    'summary' => $summary,
    'profiles' => $profileReports,
];

$outputPrefix = trim((string) $resolvedOptions['output-prefix']);
if ($outputPrefix === '') {
    $outputPrefix = 'rtu-e2e';
}

$jsonPath = rtrim($reportDir, '/').'/'.$outputPrefix.'-'.$runId.'.json';
$markdownPath = rtrim($reportDir, '/').'/'.$outputPrefix.'-'.$runId.'.md';

file_put_contents($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
file_put_contents($markdownPath, renderMarkdownReport($report));

renderConsoleSummary($report, $jsonPath, $markdownPath);

if ($failOnFailures && ($summary['fail'] ?? 0) > 0) {
    exit(1);
}

exit(0);

/**
 * @param  array<string, mixed>  $profileData
 * @return array<string, mixed>
 */
function runProfile(
    string $profileName,
    array $profileData,
    bool $allowWrite,
    string $responseMode,
    bool $debug,
): array {
    $results = [];
    $context = [];

    $target = rtrim((string) ($profileData['base_url'] ?? ''), '/');
    $password = (string) ($profileData['password'] ?? '');

    if ($target === '' || $password === '') {
        return [
            'profile' => $profileName,
            'target' => $target !== '' ? $target : null,
            'summary' => [
                'pass' => 0,
                'fail' => 1,
                'skip' => 0,
                'total' => 1,
            ],
            'results' => [[
                'name' => 'profile.validate',
                'category' => 'bootstrap',
                'mutating' => false,
                'status' => 'fail',
                'duration_ms' => 0,
                'reason' => null,
                'details' => null,
                'error' => [
                    'type' => 'InvalidArgumentException',
                    'message' => 'Profile is missing base_url or password.',
                ],
            ]],
        ];
    }

    $client = LaravelEdgelinkClient::make(
        baseUrl: $target,
        password: $password,
        referer: (string) ($profileData['referer'] ?? $target),
        verifyTls: toBool($profileData['verify_tls'] ?? false),
        timeoutSeconds: (int) ($profileData['timeout'] ?? 10),
        responseMode: $responseMode,
        rawResponse: false,
        debugEnabled: false,
    );

    foreach (buildChecks() as $check) {
        $results[] = executeCheck($check, $client, $profileData, $context, $allowWrite, $responseMode, $debug);
    }

    return [
        'profile' => $profileName,
        'target' => $target,
        'summary' => summarizeResults($results),
        'results' => $results,
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function buildChecks(): array
{
    return [
        [
            'name' => 'auth.login',
            'category' => 'auth',
            'mutating' => false,
            'runner' => static function (LaravelEdgelinkClient $client, array $profile, array &$context): array {
                $sessionId = $client->auth()->login((string) ($profile['password'] ?? ''));
                $context['session_id'] = $sessionId;

                return [
                    'session_id_length' => strlen($sessionId),
                ];
            },
        ],
        [
            'name' => 'system.version',
            'category' => 'system',
            'mutating' => false,
            'runner' => static function (LaravelEdgelinkClient $client, array $profile, array &$context, string $responseMode, bool $debug): array {
                $result = $client->system()->version(responseMode: 'envelope', debug: $debug ? true : null);
                $data = is_array($result) ? ($result['data'] ?? null) : null;
                $meta = is_array($result) ? ($result['meta'] ?? null) : null;

                $context['firmware'] = is_array($data) ? $data : null;

                return [
                    'version' => is_array($data) ? ($data['version'] ?? null) : null,
                    'released_at' => is_array($data) ? ($data['released_at'] ?? null) : null,
                    'source' => is_array($meta) ? ($meta['source'] ?? null) : null,
                ];
            },
        ],
        [
            'name' => 'system.update_info',
            'category' => 'system',
            'mutating' => false,
            'runner' => static function (LaravelEdgelinkClient $client): array {
                $result = $client->system()->updateInfo(responseMode: 'envelope');

                return [
                    'ok' => is_array($result) ? (bool) ($result['ok'] ?? false) : false,
                    'source' => is_array($result) && is_array($result['meta'] ?? null) ? ($result['meta']['source'] ?? null) : null,
                ];
            },
        ],
        [
            'name' => 'tags.list',
            'category' => 'tags',
            'mutating' => false,
            'runner' => static function (LaravelEdgelinkClient $client, array $profile, array &$context): array {
                $result = $client->tags()->list(responseMode: 'envelope', debug: true);
                $data = is_array($result) ? ($result['data'] ?? []) : [];
                $meta = is_array($result) ? ($result['meta'] ?? []) : [];
                $debugPayload = is_array($result) ? ($result['debug'] ?? []) : [];

                $tags = is_array($data) ? $data : [];
                $context['tags'] = $tags;

                $paths = [];
                if (is_array($debugPayload) && is_array($debugPayload['exchanges'] ?? null)) {
                    foreach ($debugPayload['exchanges'] as $exchange) {
                        if (! is_array($exchange)) {
                            continue;
                        }

                        $request = $exchange['request'] ?? null;
                        if (is_array($request) && isset($request['path']) && is_string($request['path'])) {
                            $paths[] = $request['path'];
                        }
                    }
                }

                $firstTagName = null;
                foreach ($tags as $tag) {
                    if (is_array($tag) && isset($tag['name']) && is_string($tag['name']) && $tag['name'] !== '') {
                        $firstTagName = $tag['name'];
                        break;
                    }
                }

                return [
                    'count' => count($tags),
                    'source' => is_array($meta) ? ($meta['source'] ?? null) : null,
                    'fallback_used' => (is_array($meta) && isset($meta['source']) && $meta['source'] !== '/data/tags'),
                    'request_paths' => $paths,
                    'first_tag' => $firstTagName,
                ];
            },
        ],
        [
            'name' => 'tags.read.first',
            'category' => 'tags',
            'mutating' => false,
            'skip_if' => static function (array $profile, array $context): ?string {
                if (! isset($context['tags']) || ! is_array($context['tags']) || $context['tags'] === []) {
                    return 'No tags available from tags.list';
                }

                return null;
            },
            'runner' => static function (LaravelEdgelinkClient $client, array $profile, array &$context): array {
                $tagName = firstNamedTag($context['tags'] ?? []);
                if ($tagName === null) {
                    throw new RuntimeException('Unable to find a tag name from tags.list output.');
                }

                $result = $client->tags()->read($tagName, responseMode: 'envelope');

                return [
                    'tag_name' => $tagName,
                    'ok' => is_array($result) ? (bool) ($result['ok'] ?? false) : false,
                    'source' => is_array($result) && is_array($result['meta'] ?? null) ? ($result['meta']['source'] ?? null) : null,
                ];
            },
        ],
        [
            'name' => 'io.read.ai',
            'category' => 'io',
            'mutating' => false,
            'runner' => static function (LaravelEdgelinkClient $client, array $profile, array &$context): array {
                $target = ioTarget($profile, 'ai');
                $result = $client->io()->read('ai', $target['slot'], $target['channel'], responseMode: 'envelope');

                return [
                    'slot' => $target['slot'],
                    'channel' => $target['channel'],
                    'ok' => is_array($result) ? (bool) ($result['ok'] ?? false) : false,
                    'value' => extractIoEnvelopeValue($result),
                ];
            },
        ],
        [
            'name' => 'io.read.di',
            'category' => 'io',
            'mutating' => false,
            'runner' => static function (LaravelEdgelinkClient $client, array $profile, array &$context): array {
                $target = ioTarget($profile, 'di');
                $result = $client->io()->read('di', $target['slot'], $target['channel'], responseMode: 'envelope');

                return [
                    'slot' => $target['slot'],
                    'channel' => $target['channel'],
                    'ok' => is_array($result) ? (bool) ($result['ok'] ?? false) : false,
                    'value' => extractIoEnvelopeValue($result),
                ];
            },
        ],
        [
            'name' => 'io.read.do',
            'category' => 'io',
            'mutating' => false,
            'runner' => static function (LaravelEdgelinkClient $client, array $profile, array &$context): array {
                $target = ioTarget($profile, 'do');
                $result = $client->io()->read('do', $target['slot'], $target['channel'], responseMode: 'envelope');

                $value = extractIoEnvelopeValue($result);
                $context['do_target'] = $target;
                $context['do_value'] = $value;

                return [
                    'slot' => $target['slot'],
                    'channel' => $target['channel'],
                    'ok' => is_array($result) ? (bool) ($result['ok'] ?? false) : false,
                    'value' => $value,
                ];
            },
        ],
        [
            'name' => 'network.lan',
            'category' => 'network',
            'mutating' => false,
            'runner' => static fn (LaravelEdgelinkClient $client): array => networkReadResult($client, 'lan'),
        ],
        [
            'name' => 'network.wlan',
            'category' => 'network',
            'mutating' => false,
            'runner' => static fn (LaravelEdgelinkClient $client): array => networkReadResult($client, 'wlan'),
        ],
        [
            'name' => 'network.cellular',
            'category' => 'network',
            'mutating' => false,
            'runner' => static fn (LaravelEdgelinkClient $client): array => networkReadResult($client, 'cellular'),
        ],
        [
            'name' => 'network.cellular_status',
            'category' => 'network',
            'mutating' => false,
            'runner' => static fn (LaravelEdgelinkClient $client): array => networkReadResult($client, 'cellularStatus'),
        ],
        [
            'name' => 'network.gps',
            'category' => 'network',
            'mutating' => false,
            'runner' => static fn (LaravelEdgelinkClient $client): array => networkReadResult($client, 'gps'),
        ],
        [
            'name' => 'network.remoteit',
            'category' => 'network',
            'mutating' => false,
            'runner' => static fn (LaravelEdgelinkClient $client): array => networkReadResult($client, 'remoteIt'),
        ],
        [
            'name' => 'network.cellular_info',
            'category' => 'network',
            'mutating' => false,
            'runner' => static fn (LaravelEdgelinkClient $client): array => networkReadResult($client, 'cellularInfo'),
        ],
        [
            'name' => 'data_logger.query',
            'category' => 'data_logger',
            'mutating' => false,
            'runner' => static function (LaravelEdgelinkClient $client): array {
                $result = $client->dataLogger()->query(responseMode: 'envelope');
                $records = is_array($result) && is_array($result['data']['records'] ?? null)
                    ? $result['data']['records']
                    : [];

                return [
                    'ok' => is_array($result) ? (bool) ($result['ok'] ?? false) : false,
                    'record_count' => count($records),
                ];
            },
        ],
        [
            'name' => 'tags.value.same',
            'category' => 'tags',
            'mutating' => true,
            'skip_if' => static function (array $profile, array $context): ?string {
                if (! isset($context['tags']) || ! is_array($context['tags'])) {
                    return 'No tags loaded from tags.list';
                }

                $tagName = (string) (($profile['write_targets']['tag_name'] ?? '') ?: '');
                if ($tagName !== '') {
                    return null;
                }

                foreach ($context['tags'] as $tag) {
                    if (is_array($tag)
                        && isset($tag['name'])
                        && is_string($tag['name'])
                        && $tag['name'] !== ''
                        && array_key_exists('value', $tag)
                        && is_scalar($tag['value'])) {
                        return null;
                    }
                }

                return 'No scalar-value tag found for value-preserving write check';
            },
            'runner' => static function (LaravelEdgelinkClient $client, array $profile, array &$context): array {
                $configuredTagName = (string) (($profile['write_targets']['tag_name'] ?? '') ?: '');
                $tagName = $configuredTagName !== '' ? $configuredTagName : null;
                $before = null;

                if ($tagName !== null) {
                    $read = $client->tags()->read($tagName, 'value', responseMode: 'data');
                    $before = normalizeTagFieldValue($read);
                } else {
                    foreach (($context['tags'] ?? []) as $tag) {
                        if (! is_array($tag)) {
                            continue;
                        }

                        if (! isset($tag['name']) || ! is_string($tag['name']) || $tag['name'] === '') {
                            continue;
                        }

                        if (! array_key_exists('value', $tag) || ! is_scalar($tag['value'])) {
                            continue;
                        }

                        $tagName = $tag['name'];
                        $before = (string) $tag['value'];
                        break;
                    }
                }

                if ($tagName === null || $before === null) {
                    throw new RuntimeException('Unable to resolve tag for value-preserving write.');
                }

                $client->tags()->value($tagName, $before, responseMode: 'envelope');

                $afterRead = $client->tags()->read($tagName, 'value', responseMode: 'data');
                $after = normalizeTagFieldValue($afterRead);

                if ($before !== $after) {
                    throw new RuntimeException(sprintf('Tag value changed for %s: before=%s after=%s', $tagName, $before, $after));
                }

                return [
                    'tag_name' => $tagName,
                    'before' => $before,
                    'after' => $after,
                    'match' => true,
                ];
            },
        ],
        [
            'name' => 'io.write.do.same',
            'category' => 'io',
            'mutating' => true,
            'runner' => static function (LaravelEdgelinkClient $client, array $profile): array {
                $target = ioTarget($profile, 'do');

                $beforeRead = $client->io()->read('do', $target['slot'], $target['channel'], responseMode: 'data');
                $before = normalizeIoReadValue($beforeRead);

                $client->io()->write('do', $target['slot'], $target['channel'], $before, responseMode: 'envelope');

                $afterRead = $client->io()->read('do', $target['slot'], $target['channel'], responseMode: 'data');
                $after = normalizeIoReadValue($afterRead);

                if ($before !== $after) {
                    throw new RuntimeException(sprintf(
                        'DO value changed at slot %d ch %d: before=%s after=%s',
                        $target['slot'],
                        $target['channel'],
                        $before,
                        $after,
                    ));
                }

                return [
                    'slot' => $target['slot'],
                    'channel' => $target['channel'],
                    'before' => $before,
                    'after' => $after,
                    'match' => true,
                ];
            },
        ],
    ];
}

/**
 * @param  array<string, mixed>  $check
 * @param  array<string, mixed>  $profile
 * @param  array<string, mixed>  $context
 * @return array<string, mixed>
 */
function executeCheck(
    array $check,
    LaravelEdgelinkClient $client,
    array $profile,
    array &$context,
    bool $allowWrite,
    string $responseMode,
    bool $debug,
): array {
    $name = (string) ($check['name'] ?? 'unknown');
    $category = (string) ($check['category'] ?? 'misc');
    $mutating = (bool) ($check['mutating'] ?? false);

    if ($mutating && ! $allowWrite) {
        return [
            'name' => $name,
            'category' => $category,
            'mutating' => true,
            'status' => 'skip',
            'duration_ms' => 0,
            'reason' => 'Mutating check disabled (use --allow-write=true)',
            'details' => null,
            'error' => null,
        ];
    }

    if (isset($check['skip_if']) && is_callable($check['skip_if'])) {
        $reason = $check['skip_if']($profile, $context);
        if (is_string($reason) && $reason !== '') {
            return [
                'name' => $name,
                'category' => $category,
                'mutating' => $mutating,
                'status' => 'skip',
                'duration_ms' => 0,
                'reason' => $reason,
                'details' => null,
                'error' => null,
            ];
        }
    }

    $startedAt = microtime(true);

    try {
        $runner = $check['runner'] ?? null;
        if (! is_callable($runner)) {
            throw new RuntimeException('Check runner is not callable.');
        }

        $details = $runner($client, $profile, $context, $responseMode, $debug);

        return [
            'name' => $name,
            'category' => $category,
            'mutating' => $mutating,
            'status' => 'pass',
            'duration_ms' => elapsedMs($startedAt),
            'reason' => null,
            'details' => $details,
            'error' => null,
        ];
    } catch (Throwable $e) {
        return [
            'name' => $name,
            'category' => $category,
            'mutating' => $mutating,
            'status' => 'fail',
            'duration_ms' => elapsedMs($startedAt),
            'reason' => null,
            'details' => null,
            'error' => [
                'type' => get_class($e),
                'message' => $e->getMessage(),
            ],
        ];
    }
}

/**
 * @param  array<int, array<string, mixed>>  $results
 * @return array<string, int>
 */
function summarizeResults(array $results): array
{
    $summary = [
        'pass' => 0,
        'fail' => 0,
        'skip' => 0,
        'total' => count($results),
    ];

    foreach ($results as $result) {
        $status = $result['status'] ?? null;
        if ($status === 'pass') {
            $summary['pass']++;
        } elseif ($status === 'fail') {
            $summary['fail']++;
        } elseif ($status === 'skip') {
            $summary['skip']++;
        }
    }

    return $summary;
}

/**
 * @param  array<int, array<string, mixed>>  $profileReports
 * @return array<string, int>
 */
function summarizeProfiles(array $profileReports): array
{
    $summary = [
        'profiles' => count($profileReports),
        'pass' => 0,
        'fail' => 0,
        'skip' => 0,
        'total' => 0,
    ];

    foreach ($profileReports as $profileReport) {
        $profileSummary = is_array($profileReport['summary'] ?? null) ? $profileReport['summary'] : [];

        $summary['pass'] += (int) ($profileSummary['pass'] ?? 0);
        $summary['fail'] += (int) ($profileSummary['fail'] ?? 0);
        $summary['skip'] += (int) ($profileSummary['skip'] ?? 0);
        $summary['total'] += (int) ($profileSummary['total'] ?? 0);
    }

    return $summary;
}

/**
 * @param  array<string, mixed>  $report
 */
function renderConsoleSummary(array $report, string $jsonPath, string $markdownPath): void
{
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];

    fwrite(STDOUT, "\nRTU E2E Summary\n");
    fwrite(STDOUT, str_repeat('-', 72)."\n");
    fwrite(STDOUT, sprintf("%-16s %-6s %-6s %-6s %-6s\n", 'Profile', 'Pass', 'Fail', 'Skip', 'Total'));
    fwrite(STDOUT, str_repeat('-', 72)."\n");

    $profiles = is_array($report['profiles'] ?? null) ? $report['profiles'] : [];

    foreach ($profiles as $profile) {
        $profileName = (string) ($profile['profile'] ?? 'unknown');
        $profileSummary = is_array($profile['summary'] ?? null) ? $profile['summary'] : [];

        fwrite(STDOUT, sprintf(
            "%-16s %-6d %-6d %-6d %-6d\n",
            $profileName,
            (int) ($profileSummary['pass'] ?? 0),
            (int) ($profileSummary['fail'] ?? 0),
            (int) ($profileSummary['skip'] ?? 0),
            (int) ($profileSummary['total'] ?? 0),
        ));
    }

    fwrite(STDOUT, str_repeat('-', 72)."\n");
    fwrite(STDOUT, sprintf(
        "%-16s %-6d %-6d %-6d %-6d\n",
        'TOTAL',
        (int) ($summary['pass'] ?? 0),
        (int) ($summary['fail'] ?? 0),
        (int) ($summary['skip'] ?? 0),
        (int) ($summary['total'] ?? 0),
    ));
    fwrite(STDOUT, str_repeat('-', 72)."\n");
    fwrite(STDOUT, "JSON report: {$jsonPath}\n");
    fwrite(STDOUT, "Markdown report: {$markdownPath}\n\n");
}

/**
 * @param  array<string, mixed>  $report
 */
function renderMarkdownReport(array $report): string
{
    $lines = [];

    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];

    $lines[] = '# RTU E2E Report';
    $lines[] = '';
    $lines[] = '- Run ID: '.(string) ($report['run_id'] ?? '');
    $lines[] = '- Generated At: '.(string) ($report['generated_at'] ?? '');
    $lines[] = '- Profiles: '.(string) ($summary['profiles'] ?? 0);
    $lines[] = '- Pass: '.(string) ($summary['pass'] ?? 0);
    $lines[] = '- Fail: '.(string) ($summary['fail'] ?? 0);
    $lines[] = '- Skip: '.(string) ($summary['skip'] ?? 0);
    $lines[] = '- Total: '.(string) ($summary['total'] ?? 0);
    $lines[] = '';

    $profiles = is_array($report['profiles'] ?? null) ? $report['profiles'] : [];

    foreach ($profiles as $profile) {
        $profileName = (string) ($profile['profile'] ?? 'unknown');
        $target = (string) ($profile['target'] ?? '');
        $profileSummary = is_array($profile['summary'] ?? null) ? $profile['summary'] : [];
        $results = is_array($profile['results'] ?? null) ? $profile['results'] : [];

        $lines[] = '## Profile: '.$profileName;
        $lines[] = '';
        $lines[] = '- Target: '.$target;
        $lines[] = '- Pass: '.(string) ($profileSummary['pass'] ?? 0);
        $lines[] = '- Fail: '.(string) ($profileSummary['fail'] ?? 0);
        $lines[] = '- Skip: '.(string) ($profileSummary['skip'] ?? 0);
        $lines[] = '';
        $lines[] = '| Check | Category | Mutating | Status | Notes |';
        $lines[] = '| --- | --- | --- | --- | --- |';

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $name = escapeMarkdownCell((string) ($result['name'] ?? ''));
            $category = escapeMarkdownCell((string) ($result['category'] ?? ''));
            $mutating = (bool) ($result['mutating'] ?? false) ? 'yes' : 'no';
            $status = escapeMarkdownCell((string) ($result['status'] ?? 'unknown'));

            $notes = '';
            if (is_string($result['reason'] ?? null) && $result['reason'] !== '') {
                $notes = $result['reason'];
            } elseif (is_array($result['error'] ?? null)) {
                $notes = (string) (($result['error']['type'] ?? 'Error').': '.($result['error']['message'] ?? 'unknown failure'));
            } elseif (is_array($result['details'] ?? null) && array_key_exists('source', $result['details'])) {
                $notes = 'source='.(string) $result['details']['source'];
            }

            $lines[] = '| '.$name.' | '.$category.' | '.$mutating.' | '.$status.' | '.escapeMarkdownCell($notes).' |';
        }

        $lines[] = '';

        $failures = array_values(array_filter($results, static function ($item): bool {
            return is_array($item) && (($item['status'] ?? null) === 'fail');
        }));

        if ($failures !== []) {
            $lines[] = '### Failures';
            $lines[] = '';

            foreach ($failures as $failure) {
                $lines[] = '- **'.(string) ($failure['name'] ?? 'unknown').'**: '.(string) (($failure['error']['type'] ?? 'Error').': '.($failure['error']['message'] ?? 'unknown'));
            }

            $lines[] = '';
        }
    }

    return implode(PHP_EOL, $lines).PHP_EOL;
}

function printUsage(): void
{
    fwrite(STDOUT, <<<'TXT'
Usage:
  php development/rtu-e2e.php [--key=value]

Options:
  --profiles=all|legacy,latest   Profile names from development/profiles.php (default: all)
  --allow-write=true|false       Enable value-preserving mutating checks (default: false)
  --fail-on-failures=true|false  Return non-zero exit code when checks fail (default: false)
  --report-dir=development/reports
  --output-prefix=rtu-e2e
  --response-mode=data|envelope  Default mode used by checks where relevant (default: data)
  --debug=true|false             Enable debug payloads for selected checks (default: false)
  --help

Examples:
  php development/rtu-e2e.php --profiles=latest
  php development/rtu-e2e.php --profiles=legacy,latest --allow-write=true
  php development/rtu-e2e.php --profiles=all --fail-on-failures=true
TXT
    );
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
 * @param  array<string, mixed>  $profiles
 * @return array<int, string>
 */
function resolveSelectedProfiles(string $selector, array $profiles): array
{
    $allProfileNames = array_keys($profiles);

    if ($selector === '' || strtolower($selector) === 'all') {
        return $allProfileNames;
    }

    $selected = array_values(array_filter(array_map('trim', explode(',', $selector)), static fn (string $name): bool => $name !== ''));
    $unknown = array_values(array_diff($selected, $allProfileNames));

    if ($unknown !== []) {
        fwrite(STDERR, 'Unknown profile(s): '.implode(', ', $unknown).PHP_EOL);
        fwrite(STDERR, 'Available profiles: '.implode(', ', $allProfileNames).PHP_EOL);
        exit(2);
    }

    return $selected;
}

function normalizeResponseMode(string $mode): string
{
    $normalized = strtolower(trim($mode));

    if (! in_array($normalized, ['data', 'envelope'], true)) {
        fwrite(STDERR, "Invalid --response-mode value. Expected data or envelope.\n");
        exit(2);
    }

    return $normalized;
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

function elapsedMs(float $startedAt): int
{
    return (int) round((microtime(true) - $startedAt) * 1000);
}

/**
 * @param  array<string, mixed>  $profile
 * @return array{slot:int,channel:int}
 */
function ioTarget(array $profile, string $type): array
{
    $targets = is_array($profile['io_targets'] ?? null) ? $profile['io_targets'] : [];
    $target = is_array($targets[$type] ?? null) ? $targets[$type] : [];

    return [
        'slot' => (int) ($target['slot'] ?? 0),
        'channel' => (int) ($target['channel'] ?? 0),
    ];
}

/**
 * @param  array<int, mixed>  $tags
 */
function firstNamedTag(array $tags): ?string
{
    foreach ($tags as $tag) {
        if (is_array($tag) && isset($tag['name']) && is_string($tag['name']) && $tag['name'] !== '') {
            return $tag['name'];
        }
    }

    return null;
}

/**
 * @param  array<string, mixed>|string|int|float|bool|null  $value
 */
function normalizeTagFieldValue(mixed $value): string
{
    if (is_array($value)) {
        if (isset($value['value']) && is_scalar($value['value'])) {
            return (string) $value['value'];
        }

        if (isset($value['Val']) && is_scalar($value['Val'])) {
            return (string) $value['Val'];
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
    }

    if ($value === null) {
        return '';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    return (string) $value;
}

/**
 * @param  array<string, mixed>|string|int|float|bool|null  $read
 */
function normalizeIoReadValue(mixed $read): string
{
    if (is_array($read)) {
        if (array_key_exists('value', $read) && is_scalar($read['value'])) {
            return (string) $read['value'];
        }

        if (array_key_exists('Val', $read) && is_scalar($read['Val'])) {
            return (string) $read['Val'];
        }
    }

    if (is_bool($read)) {
        return $read ? '1' : '0';
    }

    if ($read === null) {
        return '';
    }

    return (string) $read;
}

function extractIoEnvelopeValue(mixed $result): ?string
{
    if (! is_array($result) || ! is_array($result['data'] ?? null)) {
        return null;
    }

    $data = $result['data'];

    if (array_key_exists('value', $data) && is_scalar($data['value'])) {
        return (string) $data['value'];
    }

    return null;
}

/**
 * @return array<string, mixed>
 */
function networkReadResult(LaravelEdgelinkClient $client, string $method): array
{
    $response = $client->network()->{$method}(responseMode: 'envelope');

    return [
        'ok' => is_array($response) ? (bool) ($response['ok'] ?? false) : false,
        'source' => is_array($response) && is_array($response['meta'] ?? null) ? ($response['meta']['source'] ?? null) : null,
    ];
}

function escapeMarkdownCell(string $value): string
{
    $escaped = str_replace('|', '\\|', $value);

    return str_replace("\n", '<br>', $escaped);
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
