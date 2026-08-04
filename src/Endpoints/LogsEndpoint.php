<?php

namespace OTGH\LaravelEdgelink\Endpoints;

use OTGH\LaravelEdgelink\Endpoints\Concerns\FormatsEndpointResponses;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

class LogsEndpoint
{
    use FormatsEndpointResponses;

    /**
     * Create a new logs endpoint wrapper.
     */
    public function __construct(protected LaravelEdgelinkClient $client) {}

    /**
     * Request log file creation/export from the RTU.
     *
     * @return array|string|null Normalized, enveloped, or raw response payload.
     */
    public function create(?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->requestAndFormat(
            method: 'GET',
            path: '/sys/log_create',
            normalizer: fn (mixed $rawPayload) => $this->normalizeLogsResponse('create', [], $rawPayload),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
            meta: ['source' => '/sys/log_create', 'action' => 'create'],
        );
    }

    /**
     * Submit an explicit log message payload to the RTU.
     *
     * @param  array<string, mixed>  $payload  Log message payload.
     * @return array|string|null Normalized, enveloped, or raw response payload.
     */
    public function message(array $payload, ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        return $this->requestAndFormat(
            method: 'POST',
            path: '/sys/log_message',
            payload: $payload,
            normalizer: fn (mixed $rawPayload) => $this->normalizeLogsResponse('message', $payload, $rawPayload),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
            meta: ['source' => '/sys/log_message', 'action' => 'message'],
        );
    }

    /**
     * Normalize log action responses.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeLogsResponse(string $action, array $payload, mixed $rawPayload): array
    {
        return [
            'action' => $action,
            'payload' => $payload,
            'result' => $rawPayload,
        ];
    }
}
