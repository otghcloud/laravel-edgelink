<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use OTGH\LaravelEdgelink\Exceptions\ConfigurationException;
use OTGH\LaravelEdgelink\Exceptions\RequestException;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;
use Tests\TestCase;

class ClientCoreTest extends TestCase
{
    public function test_from_config_maps_connection_and_response_flags(): void
    {
        config()->set('edgelink', [
            'base_url' => 'https://rtu-config.local/',
            'password' => 'pw',
            'referer' => 'https://rtu-config.local',
            'verify_tls' => 'true',
            'timeout_seconds' => 25,
            'response_mode' => 'envelope',
            'raw_response' => 'false',
            'debug_enabled' => 'true',
            'debug_request_headers' => 'false',
            'debug_request_body' => 'true',
            'debug_response_headers' => 'false',
            'debug_response_body' => 'true',
        ]);

        $client = LaravelEdgelinkClient::fromConfig();

        $this->assertSame('https://rtu-config.local', $client->getConfig('base_url'));
        $this->assertSame('pw', $client->getConfig('password'));
        $this->assertTrue($client->getConfig('verify_tls'));
        $this->assertSame(25, $client->getConfig('timeout_seconds'));
        $this->assertSame('envelope', $client->getConfig('response_mode'));
        $this->assertFalse($client->getConfig('raw_response'));
        $this->assertTrue($client->getConfig('debug_enabled'));
        $this->assertFalse($client->getConfig('debug_request_headers'));
        $this->assertTrue($client->getConfig('debug_request_body'));
        $this->assertFalse($client->getConfig('debug_response_headers'));
        $this->assertTrue($client->getConfig('debug_response_body'));
    }

    public function test_request_normalizes_path_without_leading_slash(): void
    {
        Http::fake([
            'https://rtu.local/data/tags' => Http::response([], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid-1');

        $client->request('GET', 'data/tags');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://rtu.local/data/tags' && $request->method() === 'GET';
        });
    }

    public function test_request_throws_for_unsupported_method(): void
    {
        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid-1');

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('Unsupported HTTP method: TRACE');

        $client->request('TRACE', '/sys/version');
    }

    public function test_request_requires_base_url_and_password_for_login(): void
    {
        $client = LaravelEdgelinkClient::make('', '', null, true);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('ADAM RTU base URL is not configured.');

        $client->login();
    }

    public function test_ensure_authenticated_auto_logs_in_before_authenticated_request(): void
    {
        Http::fake([
            'https://rtu.local/sys/log_in' => Http::response(['session_id' => 'sid-auto'], 200),
            'https://rtu.local/data/tags' => Http::response([['name' => 'A', 'value' => '1']], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);

        $result = $client->tags()->list();

        $this->assertSame([['name' => 'A', 'value' => '1']], $result);
        $this->assertSame('sid-auto', $client->sessionId());
    }

    public function test_collect_debug_trace_returns_slice_from_index(): void
    {
        Http::fake([
            'https://rtu.local/sys/log_in' => Http::response(['session_id' => 'sid-debug'], 200),
            'https://rtu.local/sys/version' => Http::response([
                'version' => 'ADAM-3600-C2GL1A1E Standard Edition image version 2.8.0 Release Dec 29 2021',
            ], 200),
        ]);

        $client = LaravelEdgelinkClient::make(
            baseUrl: 'https://rtu.local',
            password: 'pw',
            referer: 'https://rtu.local',
            verifyTls: true,
            debugEnabled: true,
        );

        $traceStart = $client->beginDebugTrace();
        $client->login();
        $client->system()->version();

        $slice = $client->collectDebugTrace($traceStart);

        $this->assertCount(1, $slice);
        $this->assertSame('/sys/version', $slice[0]['request']['path']);
    }
}
