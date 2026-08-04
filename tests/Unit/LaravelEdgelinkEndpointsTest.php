<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use OTGH\LaravelEdgelink\Exceptions\AuthenticationException;
use OTGH\LaravelEdgelink\Exceptions\RequestException;
use OTGH\LaravelEdgelink\Exceptions\ResponseModeException;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;
use Tests\TestCase;

class LaravelEdgelinkEndpointsTest extends TestCase
{
    public function test_auth_endpoint_login_and_logout_work_through_package_client(): void
    {
        Http::fake([
            'https://rtu.local/sys/log_in' => Http::response(
                ['session_id' => 'package-session'],
                200
            ),
            'https://rtu.local/sys/log_out' => Http::response([
                'message' => 'ok',
            ], 200),
        ]);

        $client = LaravelEdgelinkClient::make(
            baseUrl: 'https://rtu.local',
            password: 'secret',
            referer: 'https://rtu.local',
            verifyTls: true,
        );

        $sessionId = $client->auth()->login('secret');
        $result = $client->auth()->logout();

        $this->assertSame('package-session', $sessionId);
        $this->assertSame(['message' => 'ok'], $result);
    }

    public function test_system_endpoint_wrappers_call_expected_paths(): void
    {
        Http::fake([
            'https://rtu.local/sys/version' => Http::response([
                'v' => 'ADAM-3600-C2GL1A1E Standard Edition image version 2.0 Release Jan 01 2024',
            ], 200),
            'https://rtu.local/sys/update_info' => Http::response(['state' => 'idle'], 200),
            'https://rtu.local/sys/control/rst' => Http::response(['message' => 'restart'], 200),
            'https://rtu.local/sys/control' => Http::response(['message' => 'control'], 200),
            'https://rtu.local/sys/control/cali' => Http::response(['message' => 'cali'], 200),
            'https://rtu.local/sys/websettings' => Http::response(['http_port' => 443], 200),
            'https://rtu.local/data/device_info/slot_0' => Http::response(['slot' => 0], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
        $client->setSessionId('sid-123');

        $this->assertSame([
            'version' => '2.0',
            'released' => 'Jan 01 2024',
            'released_at' => '2024-01-01',
        ], $client->system()->version());
        $this->assertSame(['state' => 'idle'], $client->system()->updateInfo());
        $this->assertSame(['message' => 'restart'], $client->system()->restart());
        $this->assertSame(['message' => 'control'], $client->system()->control(['mode' => 'x']));
        $this->assertSame(['message' => 'cali'], $client->system()->calibration(['target' => 'ai']));
        $this->assertSame(['http_port' => 443], $client->system()->webSettings());
        $this->assertSame(['slot' => 0], $client->system()->deviceInfo());
    }

    public function test_system_version_normalizes_local_version_key(): void
    {
        Http::fake([
            'https://rtu.local/sys/version' => Http::response([
                'Local version' => 'ADAM-3600-C2GL1 Standard Edition image version 2.8.4.5 Release Nov 18 2025',
            ], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
        $client->setSessionId('sid-123');

        $this->assertSame(
            [
                'version' => '2.8.4.5',
                'released' => 'Nov 18 2025',
                'released_at' => '2025-11-18',
            ],
            $client->system()->version(),
        );
    }

    public function test_system_version_can_return_raw_response(): void
    {
        Http::fake([
            'https://rtu.local/sys/version' => Http::response([
                'Local version' => 'ADAM-3600-C2GL1 Standard Edition image version 2.8.4.5 Release Nov 18 2025',
            ], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
        $client->setSessionId('sid-123');

        $this->assertSame(
            ['Local version' => 'ADAM-3600-C2GL1 Standard Edition image version 2.8.4.5 Release Nov 18 2025'],
            $client->system()->version(raw: true),
        );
    }

    public function test_system_version_can_return_envelope_mode(): void
    {
        Http::fake([
            'https://rtu.local/sys/version' => Http::response([
                'version' => 'ADAM-3600-C2GL1A1E Standard Edition image version 2.8.0 Release Dec 29 2021',
            ], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
        $client->setSessionId('sid-123');

        $this->assertSame([
            'ok' => true,
            'data' => [
                'version' => '2.8.0',
                'released' => 'Dec 29 2021',
                'released_at' => '2021-12-29',
            ],
            'error' => null,
            'meta' => ['source' => '/sys/version'],
        ], $client->system()->version(responseMode: 'envelope'));
    }

    public function test_system_version_falls_back_to_legacy_xml_and_normalizes_fields_on_400(): void
    {
        Http::fake([
            'https://rtu.local/sys/version' => Http::response(['error' => 'unsupported'], 400),
            'https://rtu.local/xml/version.xml' => Http::response(
                '<Firmware version="ADAM-3600-C2GL1A1E Standard Edition image version 2.8.0 Release Dec 29 2021"/>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
        $client->setSessionId('sid-123');

        $this->assertSame([
            'version' => '2.8.0',
            'released' => 'Dec 29 2021',
            'released_at' => '2021-12-29',
        ], $client->system()->version());
    }

    public function test_system_version_invalid_response_mode_throws_exception(): void
    {
        Http::fake([
            'https://rtu.local/sys/version' => Http::response([
                'version' => 'ADAM-3600-C2GL1A1E Standard Edition image version 2.8.0 Release Dec 29 2021',
            ], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
        $client->setSessionId('sid-123');

        $this->expectException(ResponseModeException::class);

        $client->system()->version(responseMode: 'invalid-mode');
    }

    public function test_io_endpoint_wrappers_call_expected_ai_ao_di_do_paths(): void
    {
        Http::fake([
            'https://rtu.local/data/ai_value/slot_0/ch_2' => Http::response(['val' => '11'], 200),
            'https://rtu.local/data/ao_value/slot_0/ch_3' => Http::response(['val' => '22'], 200),
            'https://rtu.local/data/di_value/slot_0/ch_1' => Http::response(['val' => '1'], 200),
            'https://rtu.local/data/do_value/slot_0/ch_0' => Http::response(['val' => '0'], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
        $client->setSessionId('sid-123');

        $this->assertSame(['val' => '11'], $client->io()->ai(0, 2));
        $this->assertSame(['val' => '22'], $client->io()->ao(0, 3));
        $this->assertSame(['val' => '1'], $client->io()->di(0, 1));
        $this->assertSame(['val' => '0'], $client->io()->do(0, 0));
    }

    public function test_io_endpoint_write_wrappers_send_val_payload(): void
    {
        Http::fake([
            'https://rtu.local/data/ai_value/slot_0/ch_2' => Http::response(['ok' => true], 200),
            'https://rtu.local/data/ao_value/slot_0/ch_3' => Http::response(['ok' => true], 200),
            'https://rtu.local/data/di_value/slot_0/ch_1' => Http::response(['ok' => true], 200),
            'https://rtu.local/data/do_value/slot_0/ch_0' => Http::response(['ok' => true], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
        $client->setSessionId('sid-123');

        $client->io()->setAi(0, 2, 15);
        $client->io()->setAo(0, 3, 16);
        $client->io()->setDi(0, 1, 1);
        $client->io()->setDo(0, 0, 0);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://rtu.local/data/ai_value/slot_0/ch_2'
                && $request->method() === 'PUT'
                && $request['val'] === '15';
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://rtu.local/data/ao_value/slot_0/ch_3'
                && $request->method() === 'PUT'
                && $request['val'] === '16';
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://rtu.local/data/di_value/slot_0/ch_1'
                && $request->method() === 'PUT'
                && $request['val'] === '1';
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://rtu.local/data/do_value/slot_0/ch_0'
                && $request->method() === 'PUT'
                && $request['val'] === '0';
        });
    }

    public function test_network_endpoint_wrappers_call_expected_paths(): void
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

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
        $client->setSessionId('sid-123');

        $this->assertSame(['provider' => 'x'], $client->network()->cellularInfo());
        $this->assertSame(['lan' => []], $client->network()->lan());
        $this->assertSame(['wlan' => []], $client->network()->wlan());
        $this->assertSame(['cellular' => []], $client->network()->cellular());
        $this->assertSame(['status' => 'ok'], $client->network()->cellularStatus());
        $this->assertSame(['gps' => 'on'], $client->network()->gps());
        $this->assertSame(['enabled' => true], $client->network()->remoteIt());
    }

    public function test_network_endpoint_update_wrappers_use_proper_methods_and_paths(): void
    {
        Http::fake([
            'https://rtu.local/sys/net_basic/lan/id_0' => Http::response(['ok' => true], 200),
            'https://rtu.local/sys/net_basic/wlan/id_0' => Http::response(['ok' => true], 200),
            'https://rtu.local/sys/net_basic/cellular' => Http::response(['ok' => true], 200),
            'https://rtu.local/sys/net_basic/cellular/gps' => Http::response(['ok' => true], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
        $client->setSessionId('sid-123');

        $client->network()->updateLan('id_0', ['ipv4' => ['ip' => '10.1.1.1']]);
        $client->network()->updateWlan('id_0', ['ssid' => 'wifi']);
        $client->network()->updateCellular(['apn' => 'internet']);
        $client->network()->patchGps(['enabled' => true]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://rtu.local/sys/net_basic/lan/id_0'
                && $request->method() === 'PUT';
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://rtu.local/sys/net_basic/wlan/id_0'
                && $request->method() === 'PUT';
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://rtu.local/sys/net_basic/cellular'
                && $request->method() === 'PUT';
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://rtu.local/sys/net_basic/cellular/gps'
                && $request->method() === 'PATCH';
        });
    }

    public function test_firmware_and_logs_endpoint_wrappers_call_expected_paths(): void
    {
        Http::fake([
            'https://rtu.local/sys/file_verify' => Http::response(['ok' => true], 200),
            'https://rtu.local/sys/upload' => Http::response(['ok' => true], 200),
            'https://rtu.local/sys/update' => Http::response(['ok' => true], 200),
            'https://rtu.local/sys/image/recovery' => Http::response(['ok' => true], 200),
            'https://rtu.local/sys/log_create' => Http::response(['file' => 'syslog'], 200),
            'https://rtu.local/sys/log_message' => Http::response(['ok' => true], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
        $client->setSessionId('sid-123');

        $this->assertSame(['ok' => true], $client->firmware()->verifyFile(['name' => 'fw.bin']));
        $this->assertSame(['ok' => true], $client->firmware()->upload(['name' => 'fw.bin']));
        $this->assertSame(['ok' => true], $client->firmware()->update(['force' => true]));
        $this->assertSame(['ok' => true], $client->firmware()->recoverDefaultImage());
        $this->assertSame(['file' => 'syslog'], $client->logs()->create());
        $this->assertSame(['ok' => true], $client->logs()->message(['level' => 'info', 'message' => 'hello']));
    }

    public function test_data_logger_endpoint_wrapper_calls_daq_path(): void
    {
        Http::fake([
            'https://rtu.local/data/daq*' => Http::response(['items' => []], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
        $client->setSessionId('sid-123');

        $result = $client->dataLogger()->query(['from' => 'now-1h']);

        $this->assertSame(['items' => []], $result);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://rtu.local/data/daq')
                && $request->method() === 'GET';
        });
    }

    public function test_request_helper_throws_for_unsupported_http_method(): void
    {
        $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
        $client->setSessionId('sid-123');

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('Unsupported HTTP method: TRACE');

        $client->request('TRACE', '/data/tags');
    }

    public function test_login_throws_authentication_exception_when_session_id_is_missing(): void
    {
        Http::fake([
            'https://rtu.local/sys/log_in' => Http::response(['message' => 'ok'], 200),
        ]);

        $client = LaravelEdgelinkClient::make(
            baseUrl: 'https://rtu.local',
            password: 'secret',
            referer: 'https://rtu.local',
            verifyTls: true,
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Unable to get SID from login response.');

        $client->auth()->login('secret');
    }
}
