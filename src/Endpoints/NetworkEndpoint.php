<?php

namespace OTGH\LaravelEdgelink\Endpoints;

use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

class NetworkEndpoint
{
    public function __construct(protected LaravelEdgelinkClient $client) {}

    public function cellularInfo(): array|string|null
    {
        return $this->client->requestBody('GET', '/data/cellular_info');
    }

    public function lan(): array|string|null
    {
        return $this->client->requestBody('GET', '/sys/net_basic/lan');
    }

    public function updateLan(string $id, array $payload): array|string|null
    {
        return $this->client->requestBody('PUT', '/sys/net_basic/lan/'.$id, $payload);
    }

    public function wlan(): array|string|null
    {
        return $this->client->requestBody('GET', '/sys/net_basic/wlan');
    }

    public function updateWlan(string $id, array $payload): array|string|null
    {
        return $this->client->requestBody('PUT', '/sys/net_basic/wlan/'.$id, $payload);
    }

    public function cellular(): array|string|null
    {
        return $this->client->requestBody('GET', '/sys/net_basic/cellular');
    }

    public function updateCellular(array $payload): array|string|null
    {
        return $this->client->requestBody('PUT', '/sys/net_basic/cellular', $payload);
    }

    public function cellularStatus(): array|string|null
    {
        return $this->client->requestBody('GET', '/sys/net_basic/cellular/status');
    }

    public function gps(): array|string|null
    {
        return $this->client->requestBody('GET', '/sys/net_basic/cellular/gps');
    }

    public function patchGps(array $payload): array|string|null
    {
        return $this->client->requestBody('PATCH', '/sys/net_basic/cellular/gps', $payload);
    }

    public function remoteIt(): array|string|null
    {
        return $this->client->requestBody('GET', '/data/remoteit');
    }
}
