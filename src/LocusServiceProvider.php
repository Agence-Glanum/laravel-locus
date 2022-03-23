<?php

namespace Glanum\Locus;

use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Str;
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

    public function packageBooted()
    {
        Router::macro('localize', function ($callback) {
            if (true) {
                $callback();
            }

            foreach (['en', 'fr'] as $locale) {
                $routeRegistar = (new RouteRegistrar($this))->attribute('prefix', $locale);

                $routeRegistar->group(function () use ($callback) {
                    $callback();
                });


            }

            foreach ($this->getRoutes()->getRoutes() as $route) {
                $route->setUri(Str::of($route->uri())->replace('product', 'produit'));
            }
        });
    }
}
