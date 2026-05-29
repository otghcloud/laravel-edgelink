<?php

namespace OTGH\LaravelEdgelink\Endpoints;

use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

class DataLoggerEndpoint
{
    public function __construct(protected LaravelEdgelinkClient $client) {}

    public function query(array $payload = []): array|string|null
    {
        // Spec indicates /data/daq for data logger interactions.
        return $this->client->requestBody('GET', '/data/daq', $payload);
    }
}
