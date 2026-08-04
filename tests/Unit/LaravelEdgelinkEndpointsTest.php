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
        $this->assertSame(['segment' => 'update_info', 'data' => ['state' => 'idle']], $client->system()->updateInfo());
        $this->assertSame([
            'action' => 'restart',
            'path' => '/sys/control/rst',
            'token_handshake' => false,
            'payload' => ['rst' => '1'],
            'result' => ['message' => 'restart'],
        ], $client->system()->restart());
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
        $this->assertSame(['segment' => 'device_info', 'slot' => 0, 'data' => ['slot' => 0]], $client->system()->deviceInfo());
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

    public function test_system_version_uses_client_raw_response_default_when_no_override_is_provided(): void
    {
        Http::fake([
            'https://rtu.local/sys/version' => Http::response([
                'Local version' => 'ADAM-3600-C2GL1 Standard Edition image version 2.8.4.5 Release Nov 18 2025',
            ], 200),
        ]);

        $client = LaravelEdgelinkClient::make(
            baseUrl: 'https://rtu.local',
            password: 'x',
            referer: 'https://rtu.local',
            verifyTls: true,
            timeoutSeconds: 10,
            responseMode: 'data',
            rawResponse: true,
        );
        $client->setSessionId('sid-123');

        $this->assertSame(
            ['Local version' => 'ADAM-3600-C2GL1 Standard Edition image version 2.8.4.5 Release Nov 18 2025'],
            $client->system()->version(),
        );
    }

    public function test_system_version_uses_client_response_mode_default_when_no_override_is_provided(): void
    {
        Http::fake([
            'https://rtu.local/sys/version' => Http::response([
                'version' => 'ADAM-3600-C2GL1A1E Standard Edition image version 2.8.0 Release Dec 29 2021',
            ], 200),
        ]);

        $client = LaravelEdgelinkClient::make(
            baseUrl: 'https://rtu.local',
            password: 'x',
            referer: 'https://rtu.local',
            verifyTls: true,
            timeoutSeconds: 10,
            responseMode: 'envelope',
            rawResponse: false,
        );
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
            'debug' => null,
        ], $client->system()->version());
    }

    public function test_system_version_envelope_can_include_debug_trace(): void
    {
        Http::fake([
            'https://rtu.local/sys/version' => Http::response([
                'version' => 'ADAM-3600-C2GL1A1E Standard Edition image version 2.8.0 Release Dec 29 2021',
            ], 200),
        ]);

        $client = LaravelEdgelinkClient::make(
            baseUrl: 'https://rtu.local',
            password: 'x',
            referer: 'https://rtu.local',
            verifyTls: true,
            timeoutSeconds: 10,
            responseMode: 'envelope',
            rawResponse: false,
            debugEnabled: true,
        );
        $client->setSessionId('sid-123');

        $result = $client->system()->version();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('debug', $result);
        $this->assertIsArray($result['debug']);
        $this->assertArrayHasKey('exchanges', $result['debug']);
        $this->assertNotEmpty($result['debug']['exchanges']);
        $this->assertSame('GET', $result['debug']['exchanges'][0]['request']['method']);
        $this->assertSame('/sys/version', $result['debug']['exchanges'][0]['request']['path']);
        $this->assertSame(200, $result['debug']['exchanges'][0]['response']['status']);
    }

    public function test_system_version_debug_trace_respects_field_toggles(): void
    {
        Http::fake([
            'https://rtu.local/sys/version' => Http::response([
                'version' => 'ADAM-3600-C2GL1A1E Standard Edition image version 2.8.0 Release Dec 29 2021',
            ], 200),
        ]);

        $client = LaravelEdgelinkClient::make(
            baseUrl: 'https://rtu.local',
            password: 'x',
            referer: 'https://rtu.local',
            verifyTls: true,
            timeoutSeconds: 10,
            responseMode: 'envelope',
            rawResponse: false,
            debugEnabled: true,
            debugRequestHeaders: false,
            debugRequestBody: false,
            debugResponseHeaders: true,
            debugResponseBody: false,
        );
        $client->setSessionId('sid-123');

        $result = $client->system()->version();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('debug', $result);
        $exchange = $result['debug']['exchanges'][0];

        $this->assertArrayNotHasKey('headers', $exchange['request']);
        $this->assertArrayNotHasKey('body', $exchange['request']);
        $this->assertArrayHasKey('headers', $exchange['response']);
        $this->assertArrayNotHasKey('body', $exchange['response']);
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
            'debug' => null,
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

        $this->assertSame(['type' => 'ai', 'slot' => 0, 'channel' => 2, 'value' => '11'], $client->io()->ai(0, 2));
        $this->assertSame(['type' => 'ao', 'slot' => 0, 'channel' => 3, 'value' => '22'], $client->io()->ao(0, 3));
        $this->assertSame(['type' => 'di', 'slot' => 0, 'channel' => 1, 'value' => '1'], $client->io()->di(0, 1));
        $this->assertSame(['type' => 'do', 'slot' => 0, 'channel' => 0, 'value' => '0'], $client->io()->do(0, 0));
    }

    public function test_io_endpoint_can_return_envelope_with_debug(): void
    {
        Http::fake([
            'https://rtu.local/data/ai_value/slot_0/ch_2' => Http::response(['val' => '11'], 200),
        ]);

        $client = LaravelEdgelinkClient::make(
            baseUrl: 'https://rtu.local',
            password: 'x',
            referer: 'https://rtu.local',
            verifyTls: true,
            timeoutSeconds: 10,
            responseMode: 'envelope',
            rawResponse: false,
            debugEnabled: true,
        );
        $client->setSessionId('sid-123');

        $result = $client->io()->ai(0, 2);

        $this->assertIsArray($result);
        $this->assertSame(true, $result['ok']);
        $this->assertSame(['type' => 'ai', 'slot' => 0, 'channel' => 2, 'value' => '11'], $result['data']);
        $this->assertIsArray($result['debug']);
        $this->assertSame('/data/ai_value/slot_0/ch_2', $result['meta']['source']);
    }

    public function test_io_canonical_read_and_write_methods_work(): void
    {
        Http::fake([
            'https://rtu.local/data/ai_value/slot_1/ch_4' => Http::response(['val' => '6.2'], 200),
            'https://rtu.local/data/do_value/slot_1/ch_2' => Http::response(['ok' => true], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
        $client->setSessionId('sid-123');

        $this->assertSame([
            'type' => 'ai',
            'slot' => 1,
            'channel' => 4,
            'value' => '6.2',
        ], $client->io()->read('ai', 1, 4));

        $this->assertSame([
            'type' => 'do',
            'slot' => 1,
            'channel' => 2,
            'written_value' => '1',
            'result' => ['ok' => true],
        ], $client->io()->write('do', 1, 2, true));
    }

    public function test_io_read_extracts_scalar_from_uppercase_val_key(): void
    {
        Http::fake([
            'https://rtu.local/data/do_value/slot_0/ch_0' => Http::response([
                'Ch' => 0,
                'Val' => 1,
                'Stat' => 1,
            ], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
        $client->setSessionId('sid-123');

        $this->assertSame([
            'type' => 'do',
            'slot' => 0,
            'channel' => 0,
            'value' => 1,
        ], $client->io()->read('do', 0, 0));
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

        $this->assertSame(['segment' => 'cellular_info', 'data' => ['provider' => 'x']], $client->network()->cellularInfo());
        $this->assertSame(['segment' => 'lan', 'data' => ['lan' => []]], $client->network()->lan());
        $this->assertSame(['segment' => 'wlan', 'data' => ['wlan' => []]], $client->network()->wlan());
        $this->assertSame(['segment' => 'cellular', 'data' => ['cellular' => []]], $client->network()->cellular());
        $this->assertSame(['segment' => 'cellular_status', 'data' => ['status' => 'ok']], $client->network()->cellularStatus());
        $this->assertSame(['segment' => 'gps', 'data' => ['gps' => 'on']], $client->network()->gps());
        $this->assertSame(['segment' => 'remoteit', 'data' => ['enabled' => true]], $client->network()->remoteIt());
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

    public function test_network_write_returns_normalized_contract(): void
    {
        Http::fake([
            'https://rtu.local/sys/net_basic/lan/id_0' => Http::response(['ok' => true], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
        $client->setSessionId('sid-123');

        $result = $client->network()->updateLan('id_0', ['ipv4' => ['ip' => '10.1.1.1']]);

        $this->assertSame([
            'segment' => 'lan',
            'target' => 'id_0',
            'path' => '/sys/net_basic/lan/id_0',
            'payload' => ['ipv4' => ['ip' => '10.1.1.1']],
            'result' => ['ok' => true],
        ], $result);
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

        $this->assertSame([
            'action' => 'verify_file',
            'path' => '/sys/file_verify',
            'payload' => ['name' => 'fw.bin'],
            'result' => ['ok' => true],
        ], $client->firmware()->verifyFile(['name' => 'fw.bin']));
        $this->assertSame([
            'action' => 'upload',
            'path' => '/sys/upload',
            'payload' => ['name' => 'fw.bin'],
            'result' => ['ok' => true],
        ], $client->firmware()->upload(['name' => 'fw.bin']));
        $this->assertSame([
            'action' => 'update',
            'path' => '/sys/update',
            'payload' => ['force' => true],
            'result' => ['ok' => true],
        ], $client->firmware()->update(['force' => true]));
        $this->assertSame([
            'action' => 'recover_default_image',
            'path' => '/sys/image/recovery',
            'payload' => [],
            'result' => ['ok' => true],
        ], $client->firmware()->recoverDefaultImage());
        $this->assertSame([
            'action' => 'create',
            'payload' => [],
            'result' => ['file' => 'syslog'],
        ], $client->logs()->create());
        $this->assertSame([
            'action' => 'message',
            'payload' => ['level' => 'info', 'message' => 'hello'],
            'result' => ['ok' => true],
        ], $client->logs()->message(['level' => 'info', 'message' => 'hello']));
    }

    public function test_data_logger_endpoint_wrapper_calls_daq_path(): void
    {
        Http::fake([
            'https://rtu.local/data/daq*' => Http::response(['items' => []], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
        $client->setSessionId('sid-123');

        $result = $client->dataLogger()->query(['from' => 'now-1h']);

        $this->assertSame([
            'query' => ['from' => 'now-1h'],
            'records' => [],
            'result' => ['items' => []],
        ], $result);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://rtu.local/data/daq')
                && $request->method() === 'GET';
        });
    }

    public function test_tags_endpoint_can_return_envelope_for_all(): void
    {
        Http::fake([
            'https://rtu.local/data/tags' => Http::response([
                ['name' => 'TagA', 'value' => '1'],
            ], 200),
        ]);

        $client = LaravelEdgelinkClient::make(
            baseUrl: 'https://rtu.local',
            password: 'x',
            referer: 'https://rtu.local',
            verifyTls: true,
            timeoutSeconds: 10,
            responseMode: 'envelope',
            rawResponse: false,
            debugEnabled: false,
        );
        $client->setSessionId('sid-123');

        $result = $client->tags()->all();

        $this->assertIsArray($result);
        $this->assertSame(true, $result['ok']);
        $this->assertSame([['name' => 'TagA', 'value' => '1']], $result['data']);
        $this->assertSame('/data/tags', $result['meta']['source']);
        $this->assertNull($result['debug']);
    }

    public function test_tags_canonical_read_and_value_methods_work(): void
    {
        Http::fake([
            'https://rtu.local/data/tags' => Http::response([
                'TagA' => ['value' => '12'],
            ], 200),
            'https://rtu.local/data/tags/TagA/value' => Http::response(['ok' => true], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
        $client->setSessionId('sid-123');

        $this->assertSame([
            'value' => '12',
            'name' => 'TagA',
        ], $client->tags()->read('TagA'));

        $this->assertSame([
            'path' => '/data/tags/TagA/value',
            'tag_name' => 'TagA',
            'field' => 'value',
            'payload' => ['value' => '55'],
            'result' => ['ok' => true],
        ], $client->tags()->value('TagA', 55));
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
