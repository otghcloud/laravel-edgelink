<?php

namespace OTGH\LaravelEdgelink\Endpoints;

use OTGH\LaravelEdgelink\Endpoints\Concerns\FormatsEndpointResponses;
use OTGH\LaravelEdgelink\Exceptions\ResponseTransformationException;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

class IoEndpoint
{
    use FormatsEndpointResponses;

    /**
     * @var array<int, string>
     */
    protected const SUPPORTED_TYPES = ['ai', 'ao', 'di', 'do'];

    public function __construct(protected LaravelEdgelinkClient $client) {}

    public function ai(int $slot, ?int $channel = null, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->read('ai', $slot, $channel, $raw, $responseMode, $debug);
    }

    public function ao(int $slot, ?int $channel = null, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->read('ao', $slot, $channel, $raw, $responseMode, $debug);
    }

    public function di(int $slot, ?int $channel = null, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->read('di', $slot, $channel, $raw, $responseMode, $debug);
    }

    public function do(int $slot, ?int $channel = null, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->read('do', $slot, $channel, $raw, $responseMode, $debug);
    }

    public function setAi(int $slot, int $channel, int|float|string|bool $value, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->write('ai', $slot, $channel, $value, $raw, $responseMode, $debug);
    }

    public function setAo(int $slot, int $channel, int|float|string|bool $value, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->write('ao', $slot, $channel, $value, $raw, $responseMode, $debug);
    }

    public function setDi(int $slot, int $channel, int|float|string|bool $value, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->write('di', $slot, $channel, $value, $raw, $responseMode, $debug);
    }

    public function setDo(int $slot, int $channel, int|float|string|bool $value, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->write('do', $slot, $channel, $value, $raw, $responseMode, $debug);
    }

    public function read(
        string $type,
        int $slot,
        ?int $channel = null,
        ?bool $raw = null,
        ?string $responseMode = null,
        ?bool $debug = null,
    ): array|string|null {
        $normalizedType = $this->normalizeType($type);
        $path = $this->buildReadPath($normalizedType, $slot, $channel);

        return $this->requestAndFormat(
            method: 'GET',
            path: $path,
            normalizer: fn (mixed $rawPayload) => $this->normalizeReadResponse($rawPayload, $normalizedType, $slot, $channel),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
            meta: ['source' => $path, 'type' => $normalizedType, 'slot' => $slot, 'channel' => $channel],
        );
    }

    public function write(
        string $type,
        int $slot,
        int $channel,
        int|float|string|bool $value,
        ?bool $raw = null,
        ?string $responseMode = null,
        ?bool $debug = null,
    ): array|string|null {
        $normalizedType = $this->normalizeType($type);
        $path = $this->buildWritePath($normalizedType, $slot, $channel);
        $payload = ['val' => (string) $value];

        return $this->requestAndFormat(
            method: 'PUT',
            path: $path,
            payload: $payload,
            normalizer: fn (mixed $rawPayload) => $this->normalizeWriteResponse($rawPayload, $normalizedType, $slot, $channel, (string) $value),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
            meta: ['source' => $path, 'type' => $normalizedType, 'slot' => $slot, 'channel' => $channel],
        );
    }

    protected function normalizeType(string $type): string
    {
        $normalizedType = strtolower(trim($type));

        if (! in_array($normalizedType, self::SUPPORTED_TYPES, true)) {
            throw new ResponseTransformationException('Unsupported IO type: '.$type.'. Supported types are: '.implode(', ', self::SUPPORTED_TYPES).'.');
        }

        return $normalizedType;
    }

    protected function buildReadPath(string $type, int $slot, ?int $channel): string
    {
        $path = '/data/'.$type.'_value/slot_'.$slot;

        if ($channel !== null) {
            $path .= '/ch_'.$channel;
        }

        return $path;
    }

    protected function buildWritePath(string $type, int $slot, int $channel): string
    {
        return '/data/'.$type.'_value/slot_'.$slot.'/ch_'.$channel;
    }

    protected function normalizeReadResponse(mixed $rawPayload, string $type, int $slot, ?int $channel): array
    {
        if ($channel !== null) {
            return [
                'type' => $type,
                'slot' => $slot,
                'channel' => $channel,
                'value' => $this->extractChannelValue($rawPayload),
            ];
        }

        return [
            'type' => $type,
            'slot' => $slot,
            'channels' => $this->normalizeChannels($rawPayload),
        ];
    }

    protected function normalizeWriteResponse(
        mixed $rawPayload,
        string $type,
        int $slot,
        int $channel,
        string $writtenValue,
    ): array {
        return [
            'type' => $type,
            'slot' => $slot,
            'channel' => $channel,
            'written_value' => $writtenValue,
            'result' => $rawPayload,
        ];
    }

    protected function extractChannelValue(mixed $rawPayload): mixed
    {
        if (is_array($rawPayload)) {
            foreach (['val', 'value', 'state', 'Val', 'Value', 'Stat'] as $key) {
                if (array_key_exists($key, $rawPayload) && ! is_array($rawPayload[$key])) {
                    return $rawPayload[$key];
                }
            }

            $lowerMap = [];
            foreach ($rawPayload as $key => $value) {
                if (is_string($key)) {
                    $lowerMap[strtolower($key)] = $value;
                }
            }

            foreach (['val', 'value', 'state'] as $key) {
                if (array_key_exists($key, $lowerMap) && ! is_array($lowerMap[$key])) {
                    return $lowerMap[$key];
                }
            }
        }

        return $rawPayload;
    }

    /**
     * @return array<int, array{channel:int|string,value:mixed}>
     */
    protected function normalizeChannels(mixed $rawPayload): array
    {
        if (! is_array($rawPayload)) {
            return [];
        }

        $channels = [];

        foreach ($rawPayload as $key => $value) {
            if (is_array($value)) {
                $channelValue = $this->extractChannelValue($value);
            } else {
                $channelValue = $value;
            }

            $channels[] = [
                'channel' => $key,
                'value' => $channelValue,
            ];
        }

        return $channels;
    }
}
