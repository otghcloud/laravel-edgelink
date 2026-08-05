<?php

namespace OTGH\LaravelEdgelink\Endpoints;

use OTGH\LaravelEdgelink\Endpoints\Concerns\FormatsEndpointResponses;
use OTGH\LaravelEdgelink\Exceptions\RequestException;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

class TagsEndpoint
{
    use FormatsEndpointResponses;

    /**
     * Create a new tags endpoint wrapper.
     */
    public function __construct(protected LaravelEdgelinkClient $client) {}

    /**
     * Read the full tags collection.
     *
     * @param  ?bool  $raw  Return raw payload when true.
     * @param  ?string  $responseMode  Response mode override: data or envelope.
     * @param  ?bool  $debug  Include debug trace when true.
     * @return array|string|null Normalized, enveloped, or raw response payload.
     */
    public function list(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        $traceStart = $this->client->beginDebugTrace();
        $collection = $this->requestTagCollectionWithFallback();

        return $this->formatResponse(
            normalized: $this->normalizeTagsCollection($collection['payload']),
            rawPayload: $collection['payload'],
            raw: $raw,
            responseMode: $responseMode,
            meta: ['source' => $collection['source']],
            includeDebug: $debug,
            debugPayload: ['exchanges' => $this->client->collectDebugTrace($traceStart)],
        );
    }

    /**
     * Read a specific tag, or a specific field for that tag.
     *
     * @param  string  $tagName  Tag identifier.
     * @param  ?string  $field  Optional field to read directly from the tag endpoint.
     * @param  ?bool  $raw  Return raw payload when true.
     * @param  ?string  $responseMode  Response mode override: data or envelope.
     * @param  ?bool  $debug  Include debug trace when true.
     * @return array|string|null Selected tag/field payload.
     */
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
        $collection = $this->requestTagCollectionWithFallback();
        $rawTags = $collection['payload'];
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
            meta: ['source' => $collection['source'], 'tag_name' => $tagName],
            includeDebug: $debug,
            debugPayload: ['exchanges' => $this->client->collectDebugTrace($traceStart)],
        );
    }

    /**
     * Read tags collection with fallback for older firmware that lacks /data/tags.
     *
     * @return array{payload:array<string, mixed>,source:string}
     */
    protected function requestTagCollectionWithFallback(): array
    {
        try {
            return [
                'payload' => $this->client->requestJson('GET', '/data/tags'),
                'source' => '/data/tags',
            ];
        } catch (RequestException $exception) {
            if (! $this->shouldUseLegacyTagFallback($exception)) {
                throw $exception;
            }
        }

        return [
            'payload' => $this->client->requestJson('GET', '/data/tag'),
            'source' => '/data/tag',
        ];
    }

    /**
     * Determine if tag-list request errors should trigger legacy fallback.
     */
    protected function shouldUseLegacyTagFallback(RequestException $exception): bool
    {
        return in_array($exception->statusCode(), [400, 404, 405, 500], true);
    }

    /**
     * Write to a tags endpoint path.
     *
     * @param  string  $path  Full API path to write.
     * @param  array<string, mixed>  $payload  Write payload.
     * @param  ?bool  $raw  Return raw payload when true.
     * @param  ?string  $responseMode  Response mode override: data or envelope.
     * @param  ?bool  $debug  Include debug trace when true.
     * @return array|string|null Normalized, enveloped, or raw response payload.
     */
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

    /**
     * Update a tag value at /data/tags/{tagName}/value.
     *
     * @param  string  $tagName  Tag identifier.
     * @param  int|float|string|bool  $value  Value to write.
     * @param  ?bool  $raw  Return raw payload when true.
     * @param  ?string  $responseMode  Response mode override: data or envelope.
     * @param  ?bool  $debug  Include debug trace when true.
     * @return array|string|null Normalized, enveloped, or raw response payload.
     */
    public function value(
        string $tagName,
        int|float|string|bool $value,
        ?bool $raw = null,
        ?string $responseMode = null,
        ?bool $debug = null,
    ): array|string|null {
        return $this->write(
            path: '/data/tags/'.$tagName.'/value',
            payload: ['value' => (string) $value],
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
        );
    }

    /**
     * Normalize tags payloads into a list containing explicit name keys.
     *
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

    /**
     * Normalize tag write output into a stable contract.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
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
