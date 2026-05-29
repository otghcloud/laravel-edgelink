<?php

namespace OTGH\LaravelEdgelink\Endpoints;

use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

class FirmwareEndpoint
{
    public function __construct(protected LaravelEdgelinkClient $client) {}

    public function verifyFile(array $payload): array|string|null
    {
        return $this->client->requestBody('POST', '/sys/file_verify', $payload);
    }

    public function upload(array $payload): array|string|null
    {
        return $this->client->requestBody('POST', '/sys/upload', $payload);
    }

    public function update(array $payload): array|string|null
    {
        return $this->client->requestBody('POST', '/sys/update', $payload);
    }

    public function recoverDefaultImage(array $payload = []): array|string|null
    {
        return $this->client->requestBody('POST', '/sys/image/recovery', $payload);
    }
}
