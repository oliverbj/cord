<?php

namespace Oliverbj\Cord;

use Illuminate\Support\ServiceProvider;

class CordServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('cord', function ($app) {
            return new Cord();
        });

        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'cord');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
              __DIR__.'/../config/config.php' => config_path('cord.php'),
            ], 'config');
        }
    }
}
