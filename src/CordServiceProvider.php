<?php

namespace Oliverbj\Cord;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\ServiceProvider;
use Oliverbj\Cord\Commands\CordCommand;

class CordServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('cord', function ($app) {
            return new Cord;
        });

        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'cord');

        if ($this->app->runningInConsole()) {
            $registerCommand = function (Kernel $kernel) {
                $kernel->registerCommand($this->app->make(CordCommand::class));
            };

            $this->app->afterResolving(Kernel::class, $registerCommand);

            if ($this->app->resolved(Kernel::class)) {
                $registerCommand($this->app->make(Kernel::class));
            }
        }
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $configPath = __DIR__.'/../config/config.php';

            $this->publishes([
                $configPath => config_path('cord.php'),
            ], 'config');

            $this->publishes([
                $configPath => config_path('cord.php'),
            ], 'cord-config');
        }
    }
}
