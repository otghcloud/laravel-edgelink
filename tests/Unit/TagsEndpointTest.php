<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;
use Tests\TestCase;

class TagsEndpointTest extends TestCase
{
    public function test_list_can_return_envelope_response(): void
    {
        Http::fake([
            'https://rtu.local/data/tags' => Http::response([
                ['name' => 'TagA', 'value' => '1'],
            ], 200),
        ]);

        $client = LaravelEdgelinkClient::make(
            baseUrl: 'https://rtu.local',
            password: 'pw',
            referer: 'https://rtu.local',
            verifyTls: true,
            responseMode: 'envelope',
        );
        $client->setSessionId('sid');

        $result = $client->tags()->list();

        $this->assertTrue($result['ok']);
        $this->assertSame('/data/tags', $result['meta']['source']);
        $this->assertSame([['name' => 'TagA', 'value' => '1']], $result['data']);
    }

    public function test_list_normalizes_scalar_and_missing_name_entries(): void
    {
        Http::fake([
            'https://rtu.local/data/tags' => Http::response([
                'TagA' => ['value' => '12'],
                'TagB' => ['name' => '', 'value' => '14'],
                'TagC' => '15',
            ], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $result = $client->tags()->list();

        $this->assertSame('TagA', $result[0]['name']);
        $this->assertSame('TagB', $result[1]['name']);
        $this->assertSame(['name' => 'TagC', 'value' => '15'], $result[2]);
    }

    public function test_read_returns_selected_tag_or_null_when_missing(): void
    {
        Http::fake([
            'https://rtu.local/data/tags' => Http::response([
                'TagA' => ['value' => '12'],
            ], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $this->assertSame(['value' => '12', 'name' => 'TagA'], $client->tags()->read('TagA'));
        $this->assertNull($client->tags()->read('TagMissing'));
    }

    public function test_read_field_uses_direct_tag_path(): void
    {
        Http::fake([
            'https://rtu.local/data/tags/TagA/value' => Http::response('55', 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $result = $client->tags()->read('TagA', 'value');

        $this->assertSame('55', $result);
    }

    public function test_value_and_write_normalize_response_contract(): void
    {
        Http::fake([
            'https://rtu.local/data/tags/TagA/value' => Http::response(['ok' => true], 200),
            'https://rtu.local/data/tags/TagB/unit' => Http::response(['ok' => true], 200),
        ]);

        $client = LaravelEdgelinkClient::make('https://rtu.local', 'pw', 'https://rtu.local', true);
        $client->setSessionId('sid');

        $valueResult = $client->tags()->value('TagA', 55);
        $writeResult = $client->tags()->write('/data/tags/TagB/unit', ['value' => 'V']);

        $this->assertSame([
            'path' => '/data/tags/TagA/value',
            'tag_name' => 'TagA',
            'field' => 'value',
            'payload' => ['value' => '55'],
            'result' => ['ok' => true],
        ], $valueResult);

        $this->assertSame('TagB', $writeResult['tag_name']);
        $this->assertSame('unit', $writeResult['field']);
    }
}
