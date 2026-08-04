<?php

namespace OTGH\LaravelEdgelink\Endpoints\Concerns;

use OTGH\LaravelEdgelink\Exceptions\ResponseModeException;

trait FormatsEndpointResponses
{
    /**
     * @param array<string, mixed> $meta
     */
    protected function formatResponse(mixed $data, string $responseMode = 'data', array $meta = []): mixed
    {
        if ($responseMode === 'data') {
            return $data;
        }

        if ($responseMode === 'envelope') {
            return [
                'ok' => true,
                'data' => $data,
                'error' => null,
                'meta' => $meta,
            ];
        }

        throw new ResponseModeException(
            'Unsupported response mode: '.$responseMode.'. Supported modes are: data, envelope.',
        );
    }
}
