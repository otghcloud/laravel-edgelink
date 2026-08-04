<?php

namespace OTGH\LaravelEdgelink\Facades;

use Illuminate\Support\Facades\Facade;
use OTGH\LaravelEdgelink\LaravelEdgelinkClient;

/**
 * Laravel facade for the Edgelink client binding.
 */
class LaravelEdgelink extends Facade
{
    /**
     * Get the service container binding key for the facade.
     *
     * @return class-string<LaravelEdgelinkClient>
     */
    protected static function getFacadeAccessor(): string
    {
        return LaravelEdgelinkClient::class;
    }
}
