<?php

namespace Tests\Feature;

use OTGH\LaravelEdgelink\Facades\LaravelEdgelink;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;
use Tests\TestCase;

class ContainerIntegrationTest extends TestCase
{
    public function test_service_provider_binding_returns_configured_client(): void
    {
        config()->set('edgelink', [
            'base_url' => 'https://bound.local',
            'password' => 'bound-pw',
            'referer' => 'https://bound.local',
            'verify_tls' => true,
            'timeout_seconds' => 15,
            'response_mode' => 'data',
            'raw_response' => false,
            'debug_enabled' => false,
            'debug_request_headers' => true,
            'debug_request_body' => true,
            'debug_response_headers' => true,
            'debug_response_body' => true,
        ]);

        $client = $this->app->make(LaravelEdgelinkClient::class);

        $this->assertInstanceOf(LaravelEdgelinkClient::class, $client);
        $this->assertSame('https://bound.local', $client->getConfig('base_url'));
        $this->assertSame('bound-pw', $client->getConfig('password'));
        $this->assertSame(15, $client->getConfig('timeout_seconds'));
    }

    public function test_facade_resolves_bound_client_and_proxies_methods(): void
    {
        config()->set('edgelink', [
            'base_url' => 'https://facade.local',
            'password' => 'facade-pw',
            'referer' => 'https://facade.local',
            'verify_tls' => false,
            'timeout_seconds' => 10,
            'response_mode' => 'data',
            'raw_response' => false,
            'debug_enabled' => false,
            'debug_request_headers' => true,
            'debug_request_body' => true,
            'debug_response_headers' => true,
            'debug_response_body' => true,
        ]);

        LaravelEdgelink::setSessionId('facade-sid');

        $this->assertSame('facade-sid', LaravelEdgelink::sessionId());
        $this->assertSame('https://facade.local', LaravelEdgelink::getConfig('base_url'));
    }

    public function test_from_config_defaults_referer_to_base_url_when_missing(): void
    {
        $client = LaravelEdgelinkClient::fromConfig([
            'base_url' => 'https://ref.local/',
            'password' => 'pw',
            'verify_tls' => true,
        ]);

        $this->assertSame('https://ref.local', $client->getConfig('base_url'));
        $this->assertNull($client->getConfig('referer'));
    }
}
