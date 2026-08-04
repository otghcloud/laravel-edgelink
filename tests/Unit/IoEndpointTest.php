<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use OTGH\LaravelEdgelink\Exceptions\ResponseTransformationException;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;
use Tests\TestCase;

class IoEndpointTest extends TestCase
{
    public function test_read_supports_ai_ao_di_do_channel_paths(): void
    {
        Http::fake([
            'https://rtu.local/data/ai_value/slot_0/ch_2' => Http::response(['val' => '11'], 200),
            'https://rtu.local/data/ao_value/slot_0/ch_3' => Http::response(['val' => '22'], 200),
            'https://rtu.local/data/di_value/slot_0/ch_1' => Http::response(['val' => '1'], 200),
            'https://rtu.local/data/do_value/slot_0/ch_0' => Http::response(['val' => '0'], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $this->assertSame(['type' => 'ai', 'slot' => 0, 'channel' => 2, 'value' => '11'], $client->io()->read('ai', 0, 2));
        $this->assertSame(['type' => 'ao', 'slot' => 0, 'channel' => 3, 'value' => '22'], $client->io()->read('ao', 0, 3));
        $this->assertSame(['type' => 'di', 'slot' => 0, 'channel' => 1, 'value' => '1'], $client->io()->read('di', 0, 1));
        $this->assertSame(['type' => 'do', 'slot' => 0, 'channel' => 0, 'value' => '0'], $client->io()->read('do', 0, 0));
    }

    public function test_read_without_channel_normalizes_channels_list(): void
    {
        Http::fake([
            'https://rtu.local/data/ai_value/slot_0' => Http::response([
                'ch_0' => ['Val' => 1],
                'ch_1' => ['value' => 2],
            ], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $result = $client->io()->read('ai', 0);

        $this->assertSame('ai', $result['type']);
        $this->assertSame(0, $result['slot']);
        $this->assertCount(2, $result['channels']);
        $this->assertSame(['channel' => 'ch_0', 'value' => 1], $result['channels'][0]);
        $this->assertSame(['channel' => 'ch_1', 'value' => 2], $result['channels'][1]);
    }

    public function test_write_uses_val_payload_and_string_cast(): void
    {
        Http::fake([
            'https://rtu.local/data/do_value/slot_1/ch_2' => Http::response(['ok' => true], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $result = $client->io()->write('do', 1, 2, true);

        $this->assertSame('1', $result['written_value']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://rtu.local/data/do_value/slot_1/ch_2'
                && $request->method() === 'PUT'
                && $request['val'] === '1';
        });
    }

    public function test_read_extracts_uppercase_val_keys(): void
    {
        Http::fake([
            'https://rtu.local/data/do_value/slot_0/ch_0' => Http::response([
                'Ch' => 0,
                'Val' => 1,
                'Stat' => 1,
            ], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $this->assertSame([
            'type' => 'do',
            'slot' => 0,
            'channel' => 0,
            'value' => 1,
        ], $client->io()->read('do', 0, 0));
    }

    public function test_invalid_type_throws_response_transformation_exception(): void
    {
        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $this->expectException(ResponseTransformationException::class);
        $this->expectExceptionMessage('Unsupported IO type: xx. Supported types are: ai, ao, di, do.');

        $client->io()->read('xx', 0, 0);
    }
}
