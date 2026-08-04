<?php

namespace OTGH\LaravelEdgelink\Endpoints\Concerns;

use OTGH\LaravelEdgelink\Exceptions\ResponseModeException;

trait FormatsEndpointResponses
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $meta
     */
    protected function requestAndFormat(
        string $method,
        string $path,
        array $payload = [],
        bool $requiresAuth = true,
        ?callable $normalizer = null,
        ?bool $raw = null,
        ?string $responseMode = null,
        ?bool $debug = null,
        array $meta = [],
    ): mixed {
        $traceStart = $this->client->beginDebugTrace();
        $rawPayload = $this->client->requestBody($method, $path, $payload, $requiresAuth);
        $normalized = $normalizer ? $normalizer($rawPayload) : $rawPayload;

        if (! array_key_exists('source', $meta)) {
            $meta['source'] = $path;
        }

        return $this->formatResponse(
            normalized: $normalized,
            rawPayload: $rawPayload,
            raw: $raw,
            responseMode: $responseMode,
            meta: $meta,
            includeDebug: $debug,
            debugPayload: ['exchanges' => $this->client->collectDebugTrace($traceStart)],
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function formatResponse(
        mixed $normalized,
        mixed $rawPayload,
        ?bool $raw = null,
        ?string $responseMode = null,
        array $meta = [],
        ?bool $includeDebug = null,
        ?array $debugPayload = null,
    ): mixed {
        $data = $this->shouldReturnRaw($raw) ? $rawPayload : $normalized;

        $mode = $this->resolveResponseMode($responseMode);

        if ($mode === 'data') {
            return $data;
        }

        if ($mode === 'envelope') {
            return [
                'ok' => true,
                'data' => $data,
                'error' => null,
                'meta' => $meta,
                'debug' => $this->resolveDebugPayload($includeDebug, $debugPayload),
            ];
        }

        throw new ResponseModeException(
            'Unsupported response mode: '.$mode.'. Supported modes are: data, envelope.',
        );
    }

    protected function shouldReturnRaw(?bool $raw): bool
    {
        if ($raw !== null) {
            return $raw;
        }

        return (bool) $this->client->getConfig('raw_response', false);
    }

    protected function resolveResponseMode(?string $responseMode): string
    {
        $mode = $responseMode ?? (string) $this->client->getConfig('response_mode', 'data');
        $mode = strtolower(trim($mode));

        if ($mode === '') {
            return 'data';
        }

        return $mode;
    }

    protected function shouldIncludeDebug(?bool $includeDebug): bool
    {
        if ($includeDebug !== null) {
            return $includeDebug;
        }

        return (bool) $this->client->getConfig('debug_enabled', false);
    }

    protected function resolveDebugPayload(?bool $includeDebug, ?array $debugPayload): ?array
    {
        if (! $this->shouldIncludeDebug($includeDebug)) {
            return null;
        }

        $payload = $debugPayload ?? [];

        if (! isset($payload['exchanges']) || ! is_array($payload['exchanges'])) {
            return $payload;
        }

        $includeRequestHeaders = (bool) $this->client->getConfig('debug_request_headers', true);
        $includeRequestBody = (bool) $this->client->getConfig('debug_request_body', true);
        $includeResponseHeaders = (bool) $this->client->getConfig('debug_response_headers', true);
        $includeResponseBody = (bool) $this->client->getConfig('debug_response_body', true);

        $filteredExchanges = array_map(
            static function (mixed $exchange) use (
                $includeRequestHeaders,
                $includeRequestBody,
                $includeResponseHeaders,
                $includeResponseBody,
            ): mixed {
                if (! is_array($exchange)) {
                    return $exchange;
                }

                if (isset($exchange['request']) && is_array($exchange['request'])) {
                    if (! $includeRequestHeaders) {
                        unset($exchange['request']['headers']);
                    }

                    if (! $includeRequestBody) {
                        unset($exchange['request']['body']);
                    }
                }

                if (isset($exchange['response']) && is_array($exchange['response'])) {
                    if (! $includeResponseHeaders) {
                        unset($exchange['response']['headers']);
                    }

                    if (! $includeResponseBody) {
                        unset($exchange['response']['body']);
                    }
                }

                return $exchange;
            },
            $payload['exchanges'],
        );

        $payload['exchanges'] = $filteredExchanges;

        return $payload;
    }
}
