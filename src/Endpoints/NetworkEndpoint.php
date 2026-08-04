<?php

namespace OTGH\LaravelEdgelink\Endpoints;

use OTGH\LaravelEdgelink\Endpoints\Concerns\FormatsEndpointResponses;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

class NetworkEndpoint
{
    use FormatsEndpointResponses;

    /**
     * @var array<string, string>
     */
    protected const READ_PATHS = [
        'cellular_info' => '/data/cellular_info',
        'lan' => '/sys/net_basic/lan',
        'wlan' => '/sys/net_basic/wlan',
        'cellular' => '/sys/net_basic/cellular',
        'cellular_status' => '/sys/net_basic/cellular/status',
        'gps' => '/sys/net_basic/cellular/gps',
        'remoteit' => '/data/remoteit',
    ];

    /**
     * Create a new network endpoint wrapper.
     */
    public function __construct(protected LaravelEdgelinkClient $client) {}

    /**
     * Read cellular information.
     */
    public function cellularInfo(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->read('cellular_info', raw: $raw, responseMode: $responseMode, debug: $debug);
    }

    /**
     * Read LAN settings.
     */
    public function lan(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->read('lan', raw: $raw, responseMode: $responseMode, debug: $debug);
    }

    /**
     * Update LAN settings for a specific LAN identifier.
     *
     * @param  string  $id  LAN identifier (for example id_0).
     * @param  array<string, mixed>  $payload  LAN update payload.
     */
    public function updateLan(string $id, array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->write('lan', '/sys/net_basic/lan/'.$id, $payload, $raw, $responseMode, $debug, $id);
    }

    /**
     * Read WLAN settings.
     */
    public function wlan(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->read('wlan', raw: $raw, responseMode: $responseMode, debug: $debug);
    }

    /**
     * Update WLAN settings for a specific WLAN identifier.
     *
     * @param  string  $id  WLAN identifier (for example id_0).
     * @param  array<string, mixed>  $payload  WLAN update payload.
     */
    public function updateWlan(string $id, array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->write('wlan', '/sys/net_basic/wlan/'.$id, $payload, $raw, $responseMode, $debug, $id);
    }

    /**
     * Read cellular base settings.
     */
    public function cellular(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->read('cellular', raw: $raw, responseMode: $responseMode, debug: $debug);
    }

    /**
     * Update cellular base settings.
     *
     * @param  array<string, mixed>  $payload  Cellular update payload.
     */
    public function updateCellular(array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->write('cellular', '/sys/net_basic/cellular', $payload, $raw, $responseMode, $debug);
    }

    /**
     * Read cellular connection status.
     */
    public function cellularStatus(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->read('cellular_status', raw: $raw, responseMode: $responseMode, debug: $debug);
    }

    /**
     * Read cellular GPS settings.
     */
    public function gps(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->read('gps', raw: $raw, responseMode: $responseMode, debug: $debug);
    }

    /**
     * Patch cellular GPS settings.
     *
     * @param  array<string, mixed>  $payload  GPS patch payload.
     */
    public function patchGps(array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->write('gps', '/sys/net_basic/cellular/gps', $payload, $raw, $responseMode, $debug, null, 'PATCH');
    }

    /**
     * Read remote.it settings.
     */
    public function remoteIt(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->read('remoteit', raw: $raw, responseMode: $responseMode, debug: $debug);
    }

    /**
     * Canonical network read helper.
     *
     * @param  string  $segment  Logical network segment key.
     * @return array|string|null Normalized, enveloped, or raw response payload.
     */
    public function read(
        string $segment,
        ?bool $raw = null,
        ?string $responseMode = null,
        ?bool $debug = null,
    ): array|string|null {
        $normalizedSegment = $this->normalizeSegment($segment);
        $path = self::READ_PATHS[$normalizedSegment];

        return $this->requestAndFormat(
            method: 'GET',
            path: $path,
            normalizer: fn (mixed $rawPayload) => $this->normalizeReadResponse($normalizedSegment, $rawPayload),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
            meta: ['source' => $path, 'segment' => $normalizedSegment],
        );
    }

    /**
     * Canonical network write helper.
     *
     * @param  string  $segment  Logical network segment key.
     * @param  string  $path  Full write path.
     * @param  array<string, mixed>  $payload  Write payload.
     * @param  ?string  $target  Optional target identifier (for metadata).
     * @param  string  $method  HTTP method for mutation request.
     * @return array|string|null Normalized, enveloped, or raw response payload.
     */
    public function write(
        string $segment,
        string $path,
        array $payload,
        ?bool $raw = null,
        ?string $responseMode = null,
        ?bool $debug = null,
        ?string $target = null,
        string $method = 'PUT',
    ): array|string|null {
        $normalizedSegment = $this->normalizeSegment($segment);

        return $this->requestAndFormat(
            method: $method,
            path: $path,
            payload: $payload,
            normalizer: fn (mixed $rawPayload) => $this->normalizeWriteResponse($normalizedSegment, $path, $payload, $rawPayload, $target),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
            meta: ['source' => $path, 'segment' => $normalizedSegment],
        );
    }

    /**
     * Validate and normalize network segment keys.
     */
    protected function normalizeSegment(string $segment): string
    {
        $normalizedSegment = strtolower(trim($segment));

        if (! array_key_exists($normalizedSegment, self::READ_PATHS)) {
            throw new \InvalidArgumentException('Unsupported network segment: '.$segment);
        }

        return $normalizedSegment;
    }

    /**
     * Normalize network read output.
     *
     * @return array<string, mixed>
     */
    protected function normalizeReadResponse(string $segment, mixed $rawPayload): array
    {
        return [
            'segment' => $segment,
            'data' => $rawPayload,
        ];
    }

    /**
     * Normalize network write output.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeWriteResponse(
        string $segment,
        string $path,
        array $payload,
        mixed $rawPayload,
        ?string $target,
    ): array {
        return [
            'segment' => $segment,
            'target' => $target,
            'path' => $path,
            'payload' => $payload,
            'result' => $rawPayload,
        ];
    }
}
