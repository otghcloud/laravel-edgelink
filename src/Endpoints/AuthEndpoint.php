<?php

namespace OTGH\LaravelEdgelink\Endpoints;

use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

class AuthEndpoint
{
    /**
     * Create a new authentication endpoint wrapper.
     */
    public function __construct(protected LaravelEdgelinkClient $client) {}

    /**
     * Authenticate with the RTU and return the session identifier.
     *
     * @param  string  $password  RTU password used for /sys/log_in.
     * @return string Established session identifier.
     */
    public function login(string $password): string
    {
        return $this->client->performLogin($password);
    }

    /**
     * End the active RTU session.
     *
     * @return array|string|null Raw logout payload as returned by the device.
     */
    public function logout(): array|string|null
    {
        return $this->client->performLogout();
    }
}
