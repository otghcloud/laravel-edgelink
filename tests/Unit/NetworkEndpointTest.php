<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;
use Tests\TestCase;

class NetworkEndpointTest extends TestCase
{
    public function test_convenience_read_methods_call_expected_paths(): void
    {
        Http::fake([
            'https://rtu.local/data/cellular_info' => Http::response(['provider' => 'x'], 200),
            'https://rtu.local/sys/net_basic/lan' => Http::response(['lan' => []], 200),
            'https://rtu.local/sys/net_basic/wlan' => Http::response(['wlan' => []], 200),
            'https://rtu.local/sys/net_basic/cellular' => Http::response(['cellular' => []], 200),
            'https://rtu.local/sys/net_basic/cellular/status' => Http::response(['status' => 'ok'], 200),
            'https://rtu.local/sys/net_basic/cellular/gps' => Http::response(['gps' => 'on'], 200),
            'https://rtu.local/data/remoteit' => Http::response(['enabled' => true], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $this->assertSame(['segment' => 'cellular_info', 'data' => ['provider' => 'x']], $client->network()->cellularInfo());
        $this->assertSame(['segment' => 'lan', 'data' => ['lan' => []]], $client->network()->lan());
        $this->assertSame(['segment' => 'wlan', 'data' => ['wlan' => []]], $client->network()->wlan());
        $this->assertSame(['segment' => 'cellular', 'data' => ['cellular' => []]], $client->network()->cellular());
        $this->assertSame(['segment' => 'cellular_status', 'data' => ['status' => 'ok']], $client->network()->cellularStatus());
        $this->assertSame(['segment' => 'gps', 'data' => ['gps' => 'on']], $client->network()->gps());
        $this->assertSame(['segment' => 'remoteit', 'data' => ['enabled' => true]], $client->network()->remoteIt());
    }

    public function test_write_helpers_use_expected_methods_paths_and_payloads(): void
    {
        Http::fake([
            'https://rtu.local/sys/net_basic/lan/id_0' => Http::response(['ok' => true], 200),
            'https://rtu.local/sys/net_basic/wlan/id_0' => Http::response(['ok' => true], 200),
            'https://rtu.local/sys/net_basic/cellular' => Http::response(['ok' => true], 200),
            'https://rtu.local/sys/net_basic/cellular/gps' => Http::response(['ok' => true], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $client->network()->updateLan('id_0', ['ipv4' => ['ip' => '10.1.1.1']]);
        $client->network()->updateWlan('id_0', ['ssid' => 'wifi']);
        $client->network()->updateCellular(['apn' => 'internet']);
        $client->network()->patchGps(['enabled' => true]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://rtu.local/sys/net_basic/lan/id_0' && $request->method() === 'PUT';
        });
        Http::assertSent(function ($request) {
            return $request->url() === 'https://rtu.local/sys/net_basic/wlan/id_0' && $request->method() === 'PUT';
        });
        Http::assertSent(function ($request) {
            return $request->url() === 'https://rtu.local/sys/net_basic/cellular' && $request->method() === 'PUT';
        });
        Http::assertSent(function ($request) {
            return $request->url() === 'https://rtu.local/sys/net_basic/cellular/gps' && $request->method() === 'PATCH';
        });
    }

    public function test_write_returns_normalized_contract(): void
    {
        Http::fake([
            'https://rtu.local/sys/net_basic/lan/id_0' => Http::response(['ok' => true], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $result = $client->network()->updateLan('id_0', ['ipv4' => ['ip' => '10.1.1.1']]);

        $this->assertSame([
            'segment' => 'lan',
            'target' => 'id_0',
            'path' => '/sys/net_basic/lan/id_0',
            'payload' => ['ipv4' => ['ip' => '10.1.1.1']],
            'result' => ['ok' => true],
        ], $result);
    }

    public function test_read_throws_for_unsupported_segment(): void
    {
        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported network segment: invalid-segment');

        $client->network()->read('invalid-segment');
    }
}
