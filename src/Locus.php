<?php

namespace Glanum\Locus;

use Closure;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Translation\Translator;

class Locus
{
    public Router $router;

    public Collection $oldRoutes;

    public Collection $newRoutes;

    public array $config;

    public Closure $routesCallback;

    public Collection $tempPrefixes;

    public Translator $translator;

    public function __construct(Router $router)
    {
        $this->router = $router;

        $this->oldRoutes = $this->getUpdatedRoutes();
        $this->newRoutes = collect();
        $this->tempPrefixes = collect();
        $this->config = config('locus');
        $this->translator = app('translator');
    }

    public function localize(Closure $routesCallback)
    {
        $this->routesCallback = $routesCallback;

        $this->registerRoutes();

        $this->detectNewRoutes();

        $this->removeIgnoredRoutes();

        $this->translateRoutes();

        $this->cleanup();

        $this->overrideRoutes($this->oldRoutes->merge($this->newRoutes));
    }

    protected function registerRoutes()
    {
        $this->registerDefaultRoutes();

        $this->registerLocalizedRoutes();
    }

    protected function registerDefaultRoutes()
    {
        ($this->routesCallback)();

        $this->detectNewRoutes();

        $this->newRoutes->each(function (Route $route, $key) {
            $route->isDefault = true;
        });
    }

    protected function registerLocalizedRoutes()
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

            $routeRegistrar->name($locale.'.prefix.')->group(function (){
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

        $this->newRoutes = $this->newRoutes->merge($newRoutes);
    }

    protected function removeIgnoredRoutes()
    {
        $this->newRoutes->each(function (Route $route, $key) {
            if (!property_exists($route, 'localeIgnore') || !$route->localeIgnore) {
                return;
            }

            if (property_exists($route, 'isDefault') && $route->isDefault) {
                return;
            }

            unset($this->newRoutes[$key]);
        });
    }

    protected function translateRoutes()
    {
        $this->newRoutes->each(function (Route $route) {
            if (property_exists($route, 'isDefault') && $route->isDefault) {
                return;
            }

            $action = $route->getAction();

            $locale = Str::of($action['as'])->explode('.')->first();

            $route->setUri($this->translateSegments($route->uri(), $locale));

            $action['prefix'] = $this->translateSegments($action['prefix'], $locale);
            $route->setAction($action);
        });

    }

    protected function translateSegments(string $uri, string $locale): string
    {
        $this->translator->setLocale($locale);

        return Str::of($uri)
            ->explode('/')
            ->map(function($segment) use ($locale) {
                $translationKey = 'localize.'. $segment;

                $translation = $this->translator->get($translationKey);

                if ($translation === $translationKey) {
                    return $segment;
                }

                return $translation;
            })
            ->implode('/');
    }

    protected function cleanup()
    {
        $this->removeTempPrefixes();

        $this->removeDuplicatedRoutes();
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

    protected function removeDuplicatedRoutes()
    {
        $this->newRoutes = $this->newRoutes->unique('uri');
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
