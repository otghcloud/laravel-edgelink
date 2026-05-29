<?php

namespace OTGH\LaravelEdgelink\Endpoints;

use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

class LogsEndpoint
{
    public function __construct(protected LaravelEdgelinkClient $client) {}

    public function create(): array|string|null
    {
        return $this->client->requestBody('GET', '/sys/log_create');
    }

    public function message(array $payload): array|string|null
    {
        return $this->client->requestBody('POST', '/sys/log_message', $payload);
    }
}
