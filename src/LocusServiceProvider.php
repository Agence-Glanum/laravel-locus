<?php

namespace Glanum\Locus;

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
            ->hasConfigFile()
            ->hasViews()
            ->hasCommand(LocusCommand::class);
    }

    public function packageBooted()
    {
        Router::macro('localize', function ($callback) {

            $routesBefore = $this->getRoutes()->getRoutes();

            if (true) {
                $callback();
            }

            foreach (['en', 'fr'] as $locale) {
                $routeRegistar = (new RouteRegistrar($this))->attribute('prefix', $locale);

                $routeRegistar->group(function () use ($callback) {
                    $callback();
                });
            }

            $routes = $this->getRoutes()->getRoutes();

            $routeCollection = new RouteCollection();

            foreach ($routes as $key => $route) {

                $found = Arr::first($routesBefore, function($routeBefore) use ($route) {
                    return $routeBefore->uri() === $route->uri();
                });

                if ($found !== null) {
                    $routeCollection->add($route);
                    continue;
                }

                if (property_exists($route, 'localeIgnore') && $route->localeIgnore) {
                    if (in_array(['en', 'fr'], explode('/', $route->uri()))) {
                        unset($routes[$key]);
                    }
                    continue;
                }

                $route->setUri(Str::of($route->uri())->replace('product', 'produit')->toString());
                $action =  $route->getAction();
                $action['prefix'] = Str::of($route->getAction()['prefix'])->replace('product', 'produit')->toString();
                $route->setAction($action);

                $routeCollection->add($route);
            }

            $this->setRoutes($routeCollection);
        });

        Route::macro('localeIgnore', function() {
            $this->localeIgnore = true;
        });
    }
}
