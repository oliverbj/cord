<?php

use Illuminate\Console\Application as Artisan;
use Oliverbj\Cord\Commands\CordCommand;

Artisan::starting(function ($artisan) {
    $artisan->resolve(CordCommand::class);
});
