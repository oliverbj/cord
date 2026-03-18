<?php

namespace Oliverbj\Cord;

use Illuminate\Support\ServiceProvider;
use Oliverbj\Cord\Commands\CordCommand;

class CordServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('cord', function ($app) {
            return new Cord;
        });

        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'cord');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CordCommand::class,
            ]);

            $configPath = __DIR__.'/../config/config.php';

            $this->publishes([
                $configPath => config_path('cord.php'),
            ], 'cord-config');
        }
    }
}
