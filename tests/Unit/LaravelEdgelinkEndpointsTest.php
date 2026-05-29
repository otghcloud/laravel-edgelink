<?php

use Illuminate\Support\Facades\Http;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

test('auth endpoint login and logout work through package client', function () {
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

    expect($sessionId)->toBe('package-session');
    expect($result)->toBe(['message' => 'ok']);
});

test('system endpoint wrappers call expected paths', function () {
    Http::fake([
        'https://rtu.local/sys/version' => Http::response(['v' => '2.0'], 200),
        'https://rtu.local/sys/update_info' => Http::response(['state' => 'idle'], 200),
        'https://rtu.local/sys/control/rst' => Http::response(['message' => 'restart'], 200),
        'https://rtu.local/sys/control' => Http::response(['message' => 'control'], 200),
        'https://rtu.local/sys/control/cali' => Http::response(['message' => 'cali'], 200),
        'https://rtu.local/sys/websettings' => Http::response(['http_port' => 443], 200),
        'https://rtu.local/data/device_info/slot_0' => Http::response(['slot' => 0], 200),
    ]);

    $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
    $client->setSessionId('sid-123');

    expect($client->system()->version())->toBe(['v' => '2.0']);
    expect($client->system()->updateInfo())->toBe(['state' => 'idle']);
    expect($client->system()->restart())->toBe(['message' => 'restart']);
    expect($client->system()->control(['mode' => 'x']))->toBe(['message' => 'control']);
    expect($client->system()->calibration(['target' => 'ai']))->toBe(['message' => 'cali']);
    expect($client->system()->webSettings())->toBe(['http_port' => 443]);
    expect($client->system()->deviceInfo())->toBe(['slot' => 0]);
});

test('io endpoint wrappers call expected ai ao di do paths', function () {
    Http::fake([
        'https://rtu.local/data/ai_value/slot_0/ch_2' => Http::response(['val' => '11'], 200),
        'https://rtu.local/data/ao_value/slot_0/ch_3' => Http::response(['val' => '22'], 200),
        'https://rtu.local/data/di_value/slot_0/ch_1' => Http::response(['val' => '1'], 200),
        'https://rtu.local/data/do_value/slot_0/ch_0' => Http::response(['val' => '0'], 200),
    ]);

    $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
    $client->setSessionId('sid-123');

    expect($client->io()->ai(0, 2))->toBe(['val' => '11']);
    expect($client->io()->ao(0, 3))->toBe(['val' => '22']);
    expect($client->io()->di(0, 1))->toBe(['val' => '1']);
    expect($client->io()->do(0, 0))->toBe(['val' => '0']);
});

test('io endpoint write wrappers send val payload', function () {
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
});

test('network endpoint wrappers call expected paths', function () {
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

    expect($client->network()->cellularInfo())->toBe(['provider' => 'x']);
    expect($client->network()->lan())->toBe(['lan' => []]);
    expect($client->network()->wlan())->toBe(['wlan' => []]);
    expect($client->network()->cellular())->toBe(['cellular' => []]);
    expect($client->network()->cellularStatus())->toBe(['status' => 'ok']);
    expect($client->network()->gps())->toBe(['gps' => 'on']);
    expect($client->network()->remoteIt())->toBe(['enabled' => true]);
});

test('network endpoint update wrappers use proper methods and paths', function () {
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
});

test('firmware and logs endpoint wrappers call expected paths', function () {
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

    expect($client->firmware()->verifyFile(['name' => 'fw.bin']))->toBe(['ok' => true]);
    expect($client->firmware()->upload(['name' => 'fw.bin']))->toBe(['ok' => true]);
    expect($client->firmware()->update(['force' => true]))->toBe(['ok' => true]);
    expect($client->firmware()->recoverDefaultImage())->toBe(['ok' => true]);
    expect($client->logs()->create())->toBe(['file' => 'syslog']);
    expect($client->logs()->message(['level' => 'info', 'message' => 'hello']))->toBe(['ok' => true]);
});

test('data logger endpoint wrapper calls daq path', function () {
    Http::fake([
        'https://rtu.local/data/daq*' => Http::response(['items' => []], 200),
    ]);

    $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
    $client->setSessionId('sid-123');

    $result = $client->dataLogger()->query(['from' => 'now-1h']);

    expect($result)->toBe(['items' => []]);

    Http::assertSent(function ($request) {
        return str_starts_with($request->url(), 'https://rtu.local/data/daq')
            && $request->method() === 'GET';
    });
});

test('request helper throws for unsupported HTTP method', function () {
    $client = LaravelEdgelinkClient::make('https://rtu.local', 'x', 'https://rtu.local', true);
    $client->setSessionId('sid-123');

    expect(fn () => $client->request('TRACE', '/data/tags'))
        ->toThrow(RuntimeException::class, 'Unsupported HTTP method: TRACE');
});
