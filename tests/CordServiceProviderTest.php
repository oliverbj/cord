<?php

use Illuminate\Contracts\Console\Kernel;

it('registers the manual staff command', function () {
    $commands = app(Kernel::class)->all();

    expect($commands)->toHaveKey('cord:staff:test')
        ->and($commands['cord:staff:test']->getDescription())
        ->toContain('Inspect or manually send a staff create/update request');
});