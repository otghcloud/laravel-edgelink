<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use OTGH\LaravelEdgelink\Exceptions\AuthenticationException;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;
use Tests\TestCase;

class AuthEndpointTest extends TestCase
{
    public function test_login_and_logout_work_with_auth_endpoint(): void
    {
        Http::fake([
            'https://rtu.local/sys/log_in' => Http::response(['session_id' => 'sid-auth'], 200),
            'https://rtu.local/sys/log_out' => Http::response(['ok' => true], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'secret', 'https://rtu.local', true);

        $sessionId = $client->auth()->login('secret');
        $logout = $client->auth()->logout();

        $this->assertSame('sid-auth', $sessionId);
        $this->assertSame(['ok' => true], $logout);
    }

    public function test_login_throws_when_session_id_is_missing(): void
    {
        Http::fake([
            'https://rtu.local/sys/log_in' => Http::response(['message' => 'ok'], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'secret', 'https://rtu.local', true);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Unable to get SID from login response.');

        $client->auth()->login('secret');
    }

    public function test_login_extracts_session_from_cookie_header(): void
    {
        Http::fake([
            'https://rtu.local/sys/log_in' => Http::response(['ok' => true], 200, [
                'Set-Cookie' => 'SID=sid-cookie-1; Path=/; HttpOnly',
            ]),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'secret', 'https://rtu.local', true);

        $sessionId = $client->auth()->login('secret');

        $this->assertSame('sid-cookie-1', $sessionId);
        $this->assertSame('sid-cookie-1', $client->sessionId());
    }
}
