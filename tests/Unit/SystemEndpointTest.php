<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use OTGH\LaravelEdgelink\Exceptions\RequestException;
use OTGH\LaravelEdgelink\Exceptions\ResponseModeException;
use OTGH\LaravelEdgelink\Exceptions\ResponseTransformationException;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;
use Tests\TestCase;

class SystemEndpointTest extends TestCase
{
    public function test_version_update_info_and_control_methods_return_normalized_payloads(): void
    {
        Http::fake([
            'https://rtu.local/sys/version' => Http::response([
                'v' => 'ADAM-3600-C2GL1A1E Standard Edition image version 2.0 Release Jan 01 2024',
            ], 200),
            'https://rtu.local/sys/update_info' => Http::response(['state' => 'idle'], 200),
            'https://rtu.local/sys/control' => Http::response(['message' => 'control'], 200),
            'https://rtu.local/sys/control/cali' => Http::response(['message' => 'cali'], 200),
            'https://rtu.local/sys/websettings' => Http::response(['http_port' => 443], 200),
            'https://rtu.local/data/device_info/slot_2' => Http::response(['slot' => 2], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $this->assertSame([
            'version' => '2.0',
            'released' => 'Jan 01 2024',
            'released_at' => '2024-01-01',
        ], $client->system()->version());

        $this->assertSame(['segment' => 'update_info', 'data' => ['state' => 'idle']], $client->system()->updateInfo());
        $this->assertSame([
            'action' => 'control',
            'path' => '/sys/control',
            'payload' => ['mode' => 'x'],
            'result' => ['message' => 'control'],
        ], $client->system()->control(['mode' => 'x']));
        $this->assertSame([
            'action' => 'calibration',
            'path' => '/sys/control/cali',
            'payload' => ['target' => 'ai'],
            'result' => ['message' => 'cali'],
        ], $client->system()->calibration(['target' => 'ai']));
        $this->assertSame(['segment' => 'websettings', 'data' => ['http_port' => 443]], $client->system()->webSettings());
        $this->assertSame(['segment' => 'device_info', 'slot' => 2, 'data' => ['slot' => 2]], $client->system()->deviceInfo(2));
    }

    public function test_restart_handles_token_handshake_path(): void
    {
        Http::fake([
            'https://rtu.local/sys/control/rst' => Http::response(['token' => 'abc123'], 200),
            'https://rtu.local/sys/control/rst?token=abc123' => Http::response(['ok' => true], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw-secret', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $result = $client->system()->restart();

        $this->assertSame(true, $result['token_handshake']);
        $this->assertSame(['rst' => '1', 'key' => 'pw-secret'], $result['payload']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://rtu.local/sys/control/rst?token=abc123'
                && $request->method() === 'PATCH';
        });
    }

    public function test_update_web_settings_and_update_device_info_methods_are_normalized(): void
    {
        Http::fake([
            'https://rtu.local/sys/websettings' => Http::response(['ok' => true], 200),
            'https://rtu.local/data/device_info/slot_1' => Http::response(['ok' => true], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $web = $client->system()->updateWebSettings(['https' => true]);
        $device = $client->system()->updateDeviceInfo(1, ['name' => 'edge-1']);

        $this->assertSame('update_websettings', $web['action']);
        $this->assertSame('/sys/websettings', $web['path']);
        $this->assertSame(['https' => true], $web['payload']);

        $this->assertSame('update_device_info', $device['action']);
        $this->assertSame('/data/device_info/slot_1', $device['path']);
        $this->assertSame(1, $device['slot']);
        $this->assertSame(['name' => 'edge-1'], $device['payload']);
    }

    public function test_version_falls_back_to_xml_endpoint_for_500_response(): void
    {
        Http::fake([
            'https://rtu.local/sys/version' => Http::response(['error' => 'unsupported'], 500),
            'https://rtu.local/xml/version.xml' => Http::response(
                '<Firmware version="ADAM-3600-C2GL1A1E Standard Edition image version 2.8.0 Release Dec 29 2021"/>',
                200,
                ['Content-Type' => 'application/xml']
            ),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $this->assertSame([
            'version' => '2.8.0',
            'released' => 'Dec 29 2021',
            'released_at' => '2021-12-29',
        ], $client->system()->version());
    }

    public function test_version_throws_transformation_exception_for_invalid_descriptor(): void
    {
        Http::fake([
            'https://rtu.local/sys/version' => Http::response(['version' => 'invalid-payload-without-semver'], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $this->expectException(ResponseTransformationException::class);
        $this->expectExceptionMessage('Unable to parse firmware semantic version from response.');

        $client->system()->version();
    }

    public function test_version_invalid_response_mode_throws_exception(): void
    {
        Http::fake([
            'https://rtu.local/sys/version' => Http::response([
                'version' => 'ADAM-3600-C2GL1A1E Standard Edition image version 2.8.0 Release Dec 29 2021',
            ], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $this->expectException(ResponseModeException::class);

        $client->system()->version(responseMode: 'invalid');
    }

    public function test_version_non_400_500_request_error_is_rethrown(): void
    {
        Http::fake([
            'https://rtu.local/sys/version' => Http::response(['error' => 'forbidden'], 403),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $this->expectException(RequestException::class);

        $client->system()->version();
    }
}
