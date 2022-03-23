<?php

namespace Glanum\Locus;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Glanum\Locus\Commands\LocusCommand;

class LocusServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-locus')
            ->hasConfigFile()
            ->hasViews()
            ->hasCommand(LocusCommand::class);
    }
}
