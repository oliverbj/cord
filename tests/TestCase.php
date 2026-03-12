<?php

namespace Oliverbj\Cord\Tests;

use Oliverbj\Cord\CordServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            CordServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('cord.base.eadapter_connection', [
            'url' => 'https://demo1trnservices.example.invalid/eadapter',
            'username' => 'cord-user',
            'password' => 'cord-password',
        ]);
        config()->set('cord.archive.eadapter_connection', [
            'url' => 'https://demo1prdservices.example.invalid/eadapter',
            'username' => 'archive-user',
            'password' => 'archive-password',
        ]);
        config()->set('cord.NTG_TRN.eadapter_connection', [
            'url' => 'https://demo1trnservices.example.invalid/eadapter',
            'username' => 'ntg-user',
            'password' => 'ntg-password',
        ]);
    }
}
