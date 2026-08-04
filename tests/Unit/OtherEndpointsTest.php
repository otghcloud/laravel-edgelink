<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use OTGH\LaravelEdgelink\Exceptions\RequestException;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;
use Tests\TestCase;

class OtherEndpointsTest extends TestCase
{
    public function test_firmware_endpoints_normalize_contracts(): void
    {
        Http::fake([
            'https://rtu.local/sys/file_verify' => Http::response(['ok' => true], 200),
            'https://rtu.local/sys/upload' => Http::response(['ok' => true], 200),
            'https://rtu.local/sys/update' => Http::response(['ok' => true], 200),
            'https://rtu.local/sys/image/recovery' => Http::response(['ok' => true], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $this->assertSame('verify_file', $client->firmware()->verifyFile(['name' => 'fw.bin'])['action']);
        $this->assertSame('upload', $client->firmware()->upload(['name' => 'fw.bin'])['action']);
        $this->assertSame('update', $client->firmware()->update(['force' => true])['action']);
        $this->assertSame('recover_default_image', $client->firmware()->recoverDefaultImage()['action']);
    }

    public function test_logs_endpoint_normalizes_create_and_message(): void
    {
        Http::fake([
            'https://rtu.local/sys/log_create' => Http::response(['file' => 'syslog'], 200),
            'https://rtu.local/sys/log_message' => Http::response(['ok' => true], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $create = $client->logs()->create();
        $message = $client->logs()->message(['level' => 'info', 'message' => 'hello']);

        $this->assertSame('create', $create['action']);
        $this->assertSame('message', $message['action']);
        $this->assertSame(['level' => 'info', 'message' => 'hello'], $message['payload']);
    }

    public function test_data_logger_query_normalizes_items_and_list_payloads(): void
    {
        Http::fake([
            'https://rtu.local/data/daq?from=now-1h' => Http::response(['items' => [['k' => 1]]], 200),
            'https://rtu.local/data/daq?from=now-2h' => Http::response([['k' => 2]], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $resultItems = $client->dataLogger()->query(['from' => 'now-1h']);
        $resultList = $client->dataLogger()->query(['from' => 'now-2h']);

        $this->assertSame([['k' => 1]], $resultItems['records']);
        $this->assertSame([['k' => 2]], $resultList['records']);
    }

    public function test_request_exception_unsupported_method_accessor_values_are_consistent(): void
    {
        $exception = RequestException::unsupportedMethod('trace');

        $this->assertSame('TRACE', $exception->method());
        $this->assertSame('', $exception->path());
        $this->assertNull($exception->statusCode());
        $this->assertNull($exception->responseBody());
    }
}
