<?php

namespace OTGH\LaravelEdgelink\Endpoints;

use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

class AuthEndpoint
{
    public function __construct(protected LaravelEdgelinkClient $client) {}

    public function login(string $password): string
    {
        return $this->client->performLogin($password);
    }

    public function logout(): array|string|null
    {
        return $this->client->performLogout();
    }
}
