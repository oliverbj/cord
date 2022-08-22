<?php

namespace Oliverbj\Cord;

use Oliverbj\Cord\Commands\CordCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CordServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('cord')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_cord_table')
            ->hasCommand(CordCommand::class);


    }

    public function packageRegistered(){
        $this->app->singleton('cord', function () {
            return new Cord();
        });
    }


}
