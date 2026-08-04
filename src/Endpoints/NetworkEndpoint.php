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

    public function __construct(protected LaravelEdgelinkClient $client) {}

    public function cellularInfo(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->read('cellular_info', raw: $raw, responseMode: $responseMode, debug: $debug);
    }

    public function lan(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->read('lan', raw: $raw, responseMode: $responseMode, debug: $debug);
    }

    public function updateLan(string $id, array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->write('lan', '/sys/net_basic/lan/'.$id, $payload, $raw, $responseMode, $debug, $id);
    }

    public function wlan(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->read('wlan', raw: $raw, responseMode: $responseMode, debug: $debug);
    }

    public function updateWlan(string $id, array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->write('wlan', '/sys/net_basic/wlan/'.$id, $payload, $raw, $responseMode, $debug, $id);
    }

    public function cellular(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->read('cellular', raw: $raw, responseMode: $responseMode, debug: $debug);
    }

    public function updateCellular(array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->write('cellular', '/sys/net_basic/cellular', $payload, $raw, $responseMode, $debug);
    }

    public function cellularStatus(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->read('cellular_status', raw: $raw, responseMode: $responseMode, debug: $debug);
    }

    public function gps(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->read('gps', raw: $raw, responseMode: $responseMode, debug: $debug);
    }

    public function patchGps(array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->write('gps', '/sys/net_basic/cellular/gps', $payload, $raw, $responseMode, $debug, null, 'PATCH');
    }

    public function remoteIt(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->read('remoteit', raw: $raw, responseMode: $responseMode, debug: $debug);
    }

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

    protected function normalizeSegment(string $segment): string
    {
        $normalizedSegment = strtolower(trim($segment));

        if (! array_key_exists($normalizedSegment, self::READ_PATHS)) {
            throw new \InvalidArgumentException('Unsupported network segment: '.$segment);
        }

        return $normalizedSegment;
    }

    protected function normalizeReadResponse(string $segment, mixed $rawPayload): array
    {
        return [
            'segment' => $segment,
            'data' => $rawPayload,
        ];
    }

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
