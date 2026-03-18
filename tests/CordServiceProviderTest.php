<?php

use Illuminate\Contracts\Console\Kernel;
use Oliverbj\Cord\CordServiceProvider;

it('registers the manual staff command', function () {
    $commands = app(Kernel::class)->all();

    expect($commands)->toHaveKey('cord:staff:test')
        ->and($commands['cord:staff:test']->getDescription())
        ->toContain('Inspect or manually send a staff create/update request');
});

it('publishes config only under the cord-config tag', function () {
    $cordConfigPublishPaths = CordServiceProvider::pathsToPublish(CordServiceProvider::class, 'cord-config');
    $genericConfigPublishPaths = CordServiceProvider::pathsToPublish(CordServiceProvider::class, 'config');

    $normalizedCordConfigPublishPaths = [];

    foreach ($cordConfigPublishPaths as $source => $destination) {
        $normalizedCordConfigPublishPaths[realpath($source) ?: $source] = $destination;
    }

    expect($normalizedCordConfigPublishPaths)->toBe([
        dirname(__DIR__).'/config/config.php' => config_path('cord.php'),
    ])->and($genericConfigPublishPaths)->toBe([]);
});
