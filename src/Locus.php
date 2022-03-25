<?php

namespace Glanum\Locus;

use Closure;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Locus
{
    public Router $router;

    public Collection $oldRoutes;

    public Collection $newRoutes;

    public array $config;

    public Closure $routesCallback;

    public Collection $tempPrefixes;

    public function __construct(Router $router)
    {
        $this->router = $router;

        $this->oldRoutes = $this->getUpdatedRoutes();
        $this->newRoutes = collect();
        $this->tempPrefixes = collect();
        $this->config = config('locus');
    }

    public function localize(Closure $routesCallback)
    {
        $this->routesCallback = $routesCallback;

        $this->registerRoutes();

        $this->detectNewRoutes();

        $this->removeIgnoredRoutes();

        $this->translateRoutes();

        $this->removeTempPrefixes();

        $this->overrideRoutes($this->oldRoutes->merge($this->newRoutes));
    }

    protected function registerRoutes()
    {
        foreach ($this->getConfig('locales', []) as $locale) {

            if ($this->getConfig('acceptUrlWithoutLocalePrefix', [])) {

                $prefix = Str::random(40);

                $routeRegistrar = (new RouteRegistrar($this->router))->attribute('prefix', $prefix);

                $routeRegistrar->name($locale.'.')->group(function (){
                    ($this->routesCallback)();
                });

                $this->tempPrefixes->add($prefix);
            }

            $routeRegistrar = (new RouteRegistrar($this->router))->attribute('prefix', $locale);

            $routeRegistrar->name($locale.'.')->group(function (){
                ($this->routesCallback)();
            });
        }
    }

    protected function detectNewRoutes()
    {
        $routes = $this->getUpdatedRoutes();

        $newRoutes = collect();

        $routes->each(function (Route $route) use ($newRoutes) {
            $found = Arr::first($this->oldRoutes, function ($routeBefore) use ($route) {
                return $routeBefore->uri() === $route->uri();
            });

            if ($found !== null) {
                return;
            }

            $newRoutes->add($route);
        });

        $this->newRoutes = $newRoutes;
    }

    protected function removeIgnoredRoutes()
    {
        $this->newRoutes->each(function (Route $route, $key) {
            if (!property_exists($route, 'localeIgnore') || !$route->localeIgnore) {
                return;
            }

            foreach ($this->getConfig('locales', []) as $locale) {
                if (in_array($locale, explode('/', $route->uri()))) {
                    unset($this->newRoutes[$key]);
                }
            }
        });
    }

    protected function translateRoutes()
    {
        $this->newRoutes->each(function (Route $route) {
            $route->setUri(Str::of($route->uri())->replace('product', 'produit')->toString());
            $action =  $route->getAction();
            $action['prefix'] = Str::of($route->getAction()['prefix'])->replace('product', 'produit')->toString();
            $route->setAction($action);
        });
    }

    protected function removeTempPrefixes()
    {
        $this->tempPrefixes->each(function (string $prefix) {
            $this->newRoutes->each(function (Route $route) use ($prefix) {
                $route->setUri(Str::of($route->uri())->replace($prefix.'/', '')->toString());
                $action =  $route->getAction();
                $action['prefix'] = Str::of($route->getAction()['prefix'])->replace($prefix.'/', '')->toString();
                $route->setAction($action);
            });
        });
    }

    protected function getConfig($key, $default = null)
    {
        return Arr::get($this->config, $key, $default);
    }

    protected function getUpdatedRoutes(): Collection
    {
        return collect($this->router->getRoutes()->getRoutes());
    }

    protected function overrideRoutes(Collection $routes)
    {
        $routeCollection = new RouteCollection();

        $routes->each(fn(Route $route) => $routeCollection->add($route));

        $this->router->setRoutes($routeCollection);
    }
}
