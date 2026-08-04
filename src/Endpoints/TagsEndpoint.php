<?php

namespace OTGH\LaravelEdgelink\Endpoints;

use OTGH\LaravelEdgelink\Endpoints\Concerns\FormatsEndpointResponses;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

class TagsEndpoint
{
    use FormatsEndpointResponses;

    public function __construct(protected LaravelEdgelinkClient $client) {}

    public function all(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->list($raw, $responseMode, $debug);
    }

    public function list(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->requestAndFormat(
            method: 'GET',
            path: '/data/tags',
            normalizer: fn (mixed $rawPayload) => $this->normalizeTagsCollection($rawPayload),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
        );
    }

    public function one(string $tagName, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->read($tagName, null, $raw, $responseMode, $debug);
    }

    public function read(
        string $tagName,
        ?string $field = null,
        ?bool $raw = null,
        ?string $responseMode = null,
        ?bool $debug = null,
    ): array|string|null {
        if ($field !== null && $field !== '') {
            $path = sprintf('/data/tags/%s/%s', $tagName, $field);

            return $this->requestAndFormat(
                method: 'GET',
                path: $path,
                raw: $raw,
                responseMode: $responseMode,
                debug: $debug,
                meta: ['source' => $path, 'tag_name' => $tagName, 'field' => $field],
            );
        }

        $traceStart = $this->client->beginDebugTrace();
        $rawTags = $this->client->requestJson('GET', '/data/tags');
        $normalizedTags = $this->normalizeTagsCollection($rawTags);
        $selectedTag = null;

        foreach ($normalizedTags as $tag) {
            if (($tag['name'] ?? null) === $tagName) {
                $selectedTag = $tag;
                break;
            }
        }

        return $this->formatResponse(
            normalized: $selectedTag,
            rawPayload: $selectedTag,
            raw: $raw,
            responseMode: $responseMode,
            meta: ['source' => '/data/tags', 'tag_name' => $tagName],
            includeDebug: $debug,
            debugPayload: ['exchanges' => $this->client->collectDebugTrace($traceStart)],
        );
    }

    public function update(
        string $path,
        array $payload,
        ?bool $raw = null,
        ?string $responseMode = null,
        ?bool $debug = null,
    ): array|string|null {
        return $this->write(
            path: $path,
            payload: $payload,
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
        );
    }

    public function write(
        string $path,
        array $payload,
        ?bool $raw = null,
        ?string $responseMode = null,
        ?bool $debug = null,
    ): array|string|null {
        return $this->requestAndFormat(
            method: 'PUT',
            path: $path,
            payload: $payload,
            normalizer: fn (mixed $rawPayload) => $this->normalizeWriteResponse($path, $payload, $rawPayload),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
        );
    }

    public function updateValue(
        string $tagName,
        int|float|string|bool $value,
        ?bool $raw = null,
        ?string $responseMode = null,
        ?bool $debug = null,
    ): array|string|null {
        return $this->value($tagName, $value, $raw, $responseMode, $debug);
    }

    public function value(
        string $tagName,
        int|float|string|bool $value,
        ?bool $raw = null,
        ?string $responseMode = null,
        ?bool $debug = null,
    ): array|string|null {
        return $this->update(
            '/data/tags/'.$tagName.'/value',
            ['value' => (string) $value],
            $raw,
            $responseMode,
            $debug,
        );
    }

    public function updateDoValue(
        int $slot,
        int $channel,
        int|float|string|bool $value,
        ?bool $raw = null,
        ?string $responseMode = null,
        ?bool $debug = null,
    ): array|string|null {
        return $this->update(
            path: sprintf('/data/do_value/slot_%d/ch_%d', $slot, $channel),
            payload: ['val' => (string) $value],
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
        );
    }

    public function getField(
        string $tagName,
        string $field,
        ?bool $raw = null,
        ?string $responseMode = null,
        ?bool $debug = null,
    ): array|string|null {
        return $this->read($tagName, $field, $raw, $responseMode, $debug);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeTagsCollection(mixed $rawPayload): array
    {
        if (! is_array($rawPayload)) {
            return [];
        }

        $normalized = [];

        foreach ($rawPayload as $key => $tag) {
            if (is_array($tag)) {
                if (! array_key_exists('name', $tag) || ! is_string($tag['name']) || $tag['name'] === '') {
                    $tag['name'] = is_string($key) ? $key : (string) $key;
                }

                $normalized[] = $tag;

                continue;
            }

            $normalized[] = [
                'name' => is_string($key) ? $key : (string) $key,
                'value' => $tag,
            ];
        }

        return $normalized;
    }

    protected function normalizeWriteResponse(string $path, array $payload, mixed $rawPayload): array
    {
        $tagName = null;
        $field = null;

        if (preg_match('#^/data/tags/([^/]+)/([^/]+)$#', $path, $matches) === 1) {
            $tagName = urldecode($matches[1]);
            $field = urldecode($matches[2]);
        }

        return [
            'path' => $path,
            'tag_name' => $tagName,
            'field' => $field,
            'payload' => $payload,
            'result' => $rawPayload,
        ];
    }
}
