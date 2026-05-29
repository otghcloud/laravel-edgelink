<?php

namespace OTGH\LaravelEdgelink\Endpoints;

use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

class SystemEndpoint
{
    public function __construct(protected LaravelEdgelinkClient $client) {}

    public function version(): array|string|null
    {
        return $this->client->requestBody('GET', '/sys/version');
    }

    public function updateInfo(): array|string|null
    {
        return $this->client->requestBody('GET', '/sys/update_info');
    }

    public function restart(): array|string|null
    {
        return $this->client->requestBody('PATCH', '/sys/control/rst', []);
    }

    public function control(array $payload): array|string|null
    {
        return $this->client->requestBody('PATCH', '/sys/control', $payload);
    }

    public function calibration(array $payload): array|string|null
    {
        return $this->client->requestBody('PATCH', '/sys/control/cali', $payload);
    }

    public function webSettings(): array|string|null
    {
        return $this->client->requestBody('GET', '/sys/websettings');
    }

    public function updateWebSettings(array $payload): array|string|null
    {
        return $this->client->requestBody('PUT', '/sys/websettings', $payload);
    }

    public function deviceInfo(int $slot = 0): array|string|null
    {
        return $this->client->requestBody('GET', '/data/device_info/slot_'.$slot);
    }

    public function updateDeviceInfo(int $slot, array $payload): array|string|null
    {
        return $this->client->requestBody('PATCH', '/data/device_info/slot_'.$slot, $payload);
    }
}
