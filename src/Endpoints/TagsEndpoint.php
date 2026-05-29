<?php

namespace OTGH\LaravelEdgelink\Endpoints;

use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

class TagsEndpoint
{
    public function __construct(protected LaravelEdgelinkClient $client) {}

    public function all(): array
    {
        return $this->client->requestJson('GET', '/data/tags');
    }

    public function one(string $tagName): ?array
    {
        $tags = $this->all();

        if (isset($tags[$tagName]) && is_array($tags[$tagName])) {
            return $tags[$tagName];
        }

        foreach ($tags as $tag) {
            if (is_array($tag) && ($tag['name'] ?? null) === $tagName) {
                return $tag;
            }
        }

        return null;
    }

    public function update(string $path, array $payload): array|string|null
    {
        return $this->client->requestBody('PUT', $path, $payload);
    }

    public function updateValue(string $tagName, int|float|string|bool $value): array|string|null
    {
        return $this->update('/data/tags/'.$tagName.'/value', [
            'value' => (string) $value,
        ]);
    }

    public function updateDoValue(int $slot, int $channel, int|float|string|bool $value): array|string|null
    {
        return $this->update(
            path: sprintf('/data/do_value/slot_%d/ch_%d', $slot, $channel),
            payload: ['val' => (string) $value],
        );
    }

    public function getField(string $tagName, string $field): array|string|null
    {
        return $this->client->requestBody('GET', sprintf('/data/tags/%s/%s', $tagName, $field));
    }
}
