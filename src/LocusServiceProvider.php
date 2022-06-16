<?php

namespace Glanum\Locus;

use Glanum\Locus\Http\Middleware\DetectLocale;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Arr;
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
            ->hasConfigFile('locus')
            ->hasViews()
            ->hasCommand(LocusCommand::class);
    }

    public function packageBooted()
    {
        $app = $this->app;

        $router = $app->make(Router::class);
        $router->aliasMiddleware('detect.locale', DetectLocale::class);

        Router::macro('localize', function ($config, $callback = null) use ($app) {
            $app->make(Locus::class)->localize($config, $callback);
        });

        Route::macro('localeIgnore', function() {
            $this->localeIgnore = true;
        });
    }


    public function packageRegistered()
    {
        $this->app->singleton(Locus::class, function ($app) {
            return new Locus($app['router']);
        });
    }
}
