<?php

namespace OTGH\LaravelEdgelink;

use Illuminate\Support\ServiceProvider;

class LaravelEdgelinkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/edgelink.php', 'edgelink');

        $this->app->bind(LaravelEdgelinkClient::class, function () {
            return LaravelEdgelinkClient::fromConfig();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/edgelink.php' => config_path('edgelink.php'),
        ], 'laravel-edgelink-config');
    }
}
