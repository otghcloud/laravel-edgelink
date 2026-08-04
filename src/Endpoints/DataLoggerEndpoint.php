<?php

namespace OTGH\LaravelEdgelink\Endpoints;

use OTGH\LaravelEdgelink\Endpoints\Concerns\FormatsEndpointResponses;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

class DataLoggerEndpoint
{
    use FormatsEndpointResponses;

    public function __construct(protected LaravelEdgelinkClient $client) {}

    public function query(array $payload = [], ?bool $raw = null, ?string $responseMode = null, ?bool $debug = null): array|string|null
    {
        // Spec indicates /data/daq for data logger interactions.
        return $this->requestAndFormat(
            method: 'GET',
            path: '/data/daq',
            payload: $payload,
            normalizer: fn (mixed $rawPayload) => $this->normalizeQueryResponse($payload, $rawPayload),
            raw: $raw,
            responseMode: $responseMode,
            debug: $debug,
            meta: ['source' => '/data/daq', 'action' => 'query'],
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function normalizeQueryResponse(array $query, mixed $rawPayload): array
    {
        $records = [];

        if (is_array($rawPayload)) {
            if (isset($rawPayload['items']) && is_array($rawPayload['items'])) {
                $records = $rawPayload['items'];
            } elseif (array_is_list($rawPayload)) {
                $records = $rawPayload;
            }
        }

        return [
            'query' => $query,
            'records' => $records,
            'result' => $rawPayload,
        ];
    }
}
