<?php

namespace OTGH\LaravelEdgelink;

use Illuminate\Support\ServiceProvider;

class LaravelEdgelinkServiceProvider extends ServiceProvider
{
    /**
     * Register package bindings and default configuration.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/edgelink.php', 'edgelink');

        $this->app->bind(LaravelEdgelinkClient::class, function () {
            return LaravelEdgelinkClient::fromConfig();
        });
    }

    /**
     * Publish package configuration assets.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/edgelink.php' => config_path('edgelink.php'),
        ], 'laravel-edgelink-config');
    }
}
