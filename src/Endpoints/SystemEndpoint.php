<?php

namespace OTGH\LaravelEdgelink\Endpoints;

use Carbon\Carbon;
use OTGH\LaravelEdgelink\Endpoints\Concerns\FormatsEndpointResponses;
use OTGH\LaravelEdgelink\Exceptions\RequestException;
use OTGH\LaravelEdgelink\Exceptions\ResponseTransformationException;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

class SystemEndpoint
{
    use FormatsEndpointResponses;

    /**
     * Create a new system endpoint wrapper.
     */
    public function __construct(protected LaravelEdgelinkClient $client) {}

    /**
     * Read and normalize firmware version details.
     *
     * Falls back to legacy /xml/version.xml for devices that reject /sys/version.
     *
     * @param  ?bool  $raw  Return raw payload when true.
     * @param  ?string  $responseMode  Response mode override: data or envelope.
     * @param  ?bool  $debug  Include debug trace when true.
     * @return array|string|null Normalized, enveloped, or raw response payload.
     */
    public function version(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        $traceStart = $this->client->beginDebugTrace();

        try {
            $response = $this->client->requestBody('GET', '/sys/version');

            return $this->formatVersionResponse(
                response: $response,
                raw: $raw,
                responseMode: $responseMode,
                source: '/sys/version',
                debug: $debug,
                exchanges: $this->client->collectDebugTrace($traceStart),
            );
        } catch (RequestException $e) {
            if (in_array($e->statusCode(), [400, 500], true)) {
                $legacyResponse = $this->client->requestBody('GET', '/xml/version.xml');

                return $this->formatVersionResponse(
                    response: $legacyResponse,
                    raw: $raw,
                    responseMode: $responseMode,
                    source: '/xml/version.xml',
                    debug: $debug,
                    exchanges: $this->client->collectDebugTrace($traceStart),
                );
            }

            throw $e;
        }
    }

    /**
     * Extract firmware descriptor from legacy XML responses.
     *
     * @param  string  $xml  XML payload from /xml/version.xml.
     * @return ?string Legacy firmware descriptor string.
     */
    protected function extractLegacyFirmwareVersion(string $xml): ?string
    {
        $previousUseInternalErrors = libxml_use_internal_errors(true);

        try {
            $firmwareInfo = simplexml_load_string($xml);

            if ($firmwareInfo === false) {
                return null;
            }

            $version = (string) ($firmwareInfo['version'] ?? '');

            return $version !== '' ? $version : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }
    }

    /**
     * Normalize and format version responses in either data or envelope mode.
     *
     * @param  array<int, array<string, mixed>>  $exchanges
     */
    protected function formatVersionResponse(
        array|string|null $response,
        ?bool $raw,
        ?string $responseMode,
        string $source,
        ?bool $debug,
        array $exchanges,
    ): array|string|null {
        $normalized = $this->normalizeVersionPayload($response, $source);

        return $this->formatResponse(
            normalized: $normalized,
            rawPayload: $response,
            raw: $raw,
            responseMode: $responseMode,
            meta: ['source' => $source],
            includeDebug: $debug,
            debugPayload: ['exchanges' => $exchanges],
        );
    }

    /**
     * Extract normalized semantic version fields from version payloads.
     *
     * @return array{version:string,released:?string,released_at:?string}
     */
    protected function normalizeVersionPayload(array|string|null $response, string $source): array
    {
        $descriptor = $this->extractNormalizedFirmwareDescriptor($response, $source);

        return $this->extractVersionDetails($descriptor);
    }

    /**
     * Parse semantic version and release date from firmware descriptor text.
     *
     * @return array{version:string,released:?string,released_at:?string}
     */
    protected function extractVersionDetails(string $descriptor): array
    {
        if (! preg_match('/image\s+version\s+([0-9]+(?:\.[0-9]+)+)/i', $descriptor, $versionMatch)) {
            throw new ResponseTransformationException('Unable to parse firmware semantic version from response.');
        }

        $released = null;
        $releasedAt = null;

        if (preg_match('/release\s+([A-Za-z]{3}\s+\d{1,2}\s+\d{4})/i', $descriptor, $releaseMatch)) {
            $released = preg_replace('/\s+/', ' ', trim($releaseMatch[1]));

            try {
                $releasedAt = Carbon::createFromFormat('M j Y', $released)->toDateString();
            } catch (\Throwable $e) {
                throw new ResponseTransformationException('Unable to parse firmware release date.', 0, $e);
            }
        }

        return [
            'version' => $versionMatch[1],
            'released' => $released,
            'released_at' => $releasedAt,
        ];
    }

    /**
     * Normalize firmware descriptor from API or legacy XML responses.
     */
    protected function extractNormalizedFirmwareDescriptor(array|string|null $response, string $source): string
    {
        if (is_array($response)) {
            foreach (['version', 'Local version', 'local_version', 'v'] as $key) {
                if (isset($response[$key]) && is_scalar($response[$key])) {
                    return (string) $response[$key];
                }
            }
        }

        if (is_string($response)) {
            if ($source === '/xml/version.xml') {
                $legacyVersion = $this->extractLegacyFirmwareVersion($response);
                if ($legacyVersion !== null) {
                    return $legacyVersion;
                }
            }

            if ($response !== '') {
                return $response;
            }
        }

        throw new ResponseTransformationException('Unable to normalize firmware version response.');
    }

    /**
     * Read update status information.
     *
     * @return array|string|null Normalized, enveloped, or raw response payload.
     */
    public function updateInfo(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->requestAndFormat(
            method: 'GET',
            path: '/sys/update_info',
            normalizer: fn (mixed $rawPayload) => $this->normalizeSystemReadResponse('update_info', $rawPayload),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
            meta: ['source' => '/sys/update_info', 'segment' => 'update_info'],
        );
    }

    /**
     * Trigger device restart with optional token handshake flow.
     *
     * @return array|string|null Normalized, enveloped, or raw response payload.
     */
    public function restart(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        $traceStart = $this->client->beginDebugTrace();
        $req = $this->client->requestBody('PATCH', '/sys/control/rst');
        $response = $req;
        $payload = ['rst' => '1'];
        $usedToken = false;

        if (is_array($req) && isset($req['token'])) {
            // Newer firmwares give us a restart token, so we need to send it back alongside our password to actually restart the device
            $usedToken = true;
            $payload = ['rst' => '1', 'key' => $this->client->getConfig('password')];
            $response = $this->client->requestJson(
                'PATCH',
                '/sys/control/rst?token='.urlencode($req['token']),
                $payload,
            );
        }

        $normalized = $this->normalizeSystemWriteResponse(
            action: 'restart',
            path: '/sys/control/rst',
            payload: $payload,
            rawPayload: $response,
            extra: ['token_handshake' => $usedToken],
        );

        return $this->formatResponse(
            normalized: $normalized,
            rawPayload: $response,
            raw: $raw,
            responseMode: $responseMode,
            meta: ['source' => '/sys/control/rst', 'action' => 'restart'],
            includeDebug: $debug,
            debugPayload: ['exchanges' => $this->client->collectDebugTrace($traceStart)],
        );
    }

    /**
     * Send system control mutation payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array|string|null Normalized, enveloped, or raw response payload.
     */
    public function control(array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->requestAndFormat(
            method: 'PATCH',
            path: '/sys/control',
            payload: $payload,
            normalizer: fn (mixed $rawPayload) => $this->normalizeSystemWriteResponse('control', '/sys/control', $payload, $rawPayload),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
            meta: ['source' => '/sys/control', 'action' => 'control'],
        );
    }

    /**
     * Send system calibration payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array|string|null Normalized, enveloped, or raw response payload.
     */
    public function calibration(array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->requestAndFormat(
            method: 'PATCH',
            path: '/sys/control/cali',
            payload: $payload,
            normalizer: fn (mixed $rawPayload) => $this->normalizeSystemWriteResponse('calibration', '/sys/control/cali', $payload, $rawPayload),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
            meta: ['source' => '/sys/control/cali', 'action' => 'calibration'],
        );
    }

    /**
     * Read web settings.
     *
     * @return array|string|null Normalized, enveloped, or raw response payload.
     */
    public function webSettings(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->requestAndFormat(
            method: 'GET',
            path: '/sys/websettings',
            normalizer: fn (mixed $rawPayload) => $this->normalizeSystemReadResponse('websettings', $rawPayload),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
            meta: ['source' => '/sys/websettings', 'segment' => 'websettings'],
        );
    }

    /**
     * Update web settings.
     *
     * @param  array<string, mixed>  $payload
     * @return array|string|null Normalized, enveloped, or raw response payload.
     */
    public function updateWebSettings(array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->requestAndFormat(
            method: 'PUT',
            path: '/sys/websettings',
            payload: $payload,
            normalizer: fn (mixed $rawPayload) => $this->normalizeSystemWriteResponse('update_websettings', '/sys/websettings', $payload, $rawPayload),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
            meta: ['source' => '/sys/websettings', 'action' => 'update_websettings'],
        );
    }

    /**
     * Read device info for a specific slot.
     *
     * @param  int  $slot  Device info slot index.
     * @return array|string|null Normalized, enveloped, or raw response payload.
     */
    public function deviceInfo(int $slot = 0, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        $path = '/data/device_info/slot_'.$slot;

        return $this->requestAndFormat(
            method: 'GET',
            path: $path,
            normalizer: fn (mixed $rawPayload) => $this->normalizeSystemReadResponse('device_info', $rawPayload, ['slot' => $slot]),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
            meta: ['source' => $path, 'segment' => 'device_info', 'slot' => $slot],
        );
    }

    /**
     * Update device info for a specific slot.
     *
     * @param  int  $slot  Device info slot index.
     * @param  array<string, mixed>  $payload
     * @return array|string|null Normalized, enveloped, or raw response payload.
     */
    public function updateDeviceInfo(int $slot, array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        $path = '/data/device_info/slot_'.$slot;

        return $this->requestAndFormat(
            method: 'PATCH',
            path: $path,
            payload: $payload,
            normalizer: fn (mixed $rawPayload) => $this->normalizeSystemWriteResponse('update_device_info', $path, $payload, $rawPayload, ['slot' => $slot]),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
            meta: ['source' => $path, 'action' => 'update_device_info', 'slot' => $slot],
        );
    }

    /**
     * Normalize read-style system responses.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function normalizeSystemReadResponse(string $segment, mixed $rawPayload, array $extra = []): array
    {
        return [
            'segment' => $segment,
            ...$extra,
            'data' => $rawPayload,
        ];
    }

    /**
     * Normalize write-style system responses.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function normalizeSystemWriteResponse(
        string $action,
        string $path,
        array $payload,
        mixed $rawPayload,
        array $extra = [],
    ): array {
        return [
            'action' => $action,
            'path' => $path,
            ...$extra,
            'payload' => $payload,
            'result' => $rawPayload,
        ];
    }
}
