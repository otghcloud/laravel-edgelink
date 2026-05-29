<?php

namespace OTGH\LaravelEdgelink\Endpoints;

use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

class IoEndpoint
{
    public function __construct(protected LaravelEdgelinkClient $client) {}

    public function ai(int $slot, ?int $channel = null): array|string|null
    {
        return $this->getByType('ai', $slot, $channel);
    }

    public function ao(int $slot, ?int $channel = null): array|string|null
    {
        return $this->getByType('ao', $slot, $channel);
    }

    public function di(int $slot, ?int $channel = null): array|string|null
    {
        return $this->getByType('di', $slot, $channel);
    }

    public function do(int $slot, ?int $channel = null): array|string|null
    {
        return $this->getByType('do', $slot, $channel);
    }

    public function setAi(int $slot, int $channel, int|float|string|bool $value): array|string|null
    {
        return $this->setByType('ai', $slot, $channel, $value);
    }

    public function setAo(int $slot, int $channel, int|float|string|bool $value): array|string|null
    {
        return $this->setByType('ao', $slot, $channel, $value);
    }

    public function setDi(int $slot, int $channel, int|float|string|bool $value): array|string|null
    {
        return $this->setByType('di', $slot, $channel, $value);
    }

    public function setDo(int $slot, int $channel, int|float|string|bool $value): array|string|null
    {
        return $this->setByType('do', $slot, $channel, $value);
    }

    protected function getByType(string $type, int $slot, ?int $channel = null): array|string|null
    {
        $path = '/data/'.$type.'_value/slot_'.$slot;

        if ($channel !== null) {
            $path .= '/ch_'.$channel;
        }

        return $this->client->requestBody('GET', $path);
    }

    protected function setByType(string $type, int $slot, int $channel, int|float|string|bool $value): array|string|null
    {
        return $this->client->requestBody(
            'PUT',
            '/data/'.$type.'_value/slot_'.$slot.'/ch_'.$channel,
            ['val' => (string) $value],
        );
    }
}
