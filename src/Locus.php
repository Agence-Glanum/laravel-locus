<?php

namespace Glanum\Locus;

use Closure;
use Glanum\Locus\enums\config\Method;
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

    public Config $config;

    public Closure $routesCallback;

    public Collection $tempPrefixes;

    public Translator $translator;

    public function __construct(Router $router)
    {
        $this->router = $router;

        $this->oldRoutes = $this->getUpdatedRoutes();
        $this->newRoutes = collect();
        $this->tempPrefixes = collect();
        $this->config = new Config();
        $this->translator = app('translator');
    }


    /**
     * @param  array|Closure  $config
     * @param  Closure|null  $routesCallback
     */
    public function localize($config, Closure $routesCallback = null)
    {
        if (is_array($config)) {
            $this->config->setConfig($config);
        } else {
            $routesCallback = $config;
        }

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
        if ($this->config->getMethod() === Method::DOMAIN || $this->config->getMethod() === Method::HIDDEN) {
            $this->registerDefaultRoutes();
        }

        $this->registerLocalizedRoutes();
    }

    protected function registerDefaultRoutes()
    {
        ($this->routesCallback)();

        $this->detectNewRoutes();

        $this->newRoutes->each(function (Route $route) {
            $route->isDefault = true;
        });
    }

    protected function registerLocalizedRoutes()
    {
        foreach ($this->config->getLocales() as $locale) {
            if ($this->config->getMethod() === Method::DOMAIN || $this->config->getMethod() === Method::HIDDEN) {

                $prefix = Str::random(40);

                $routeRegistrar = (new RouteRegistrar($this->router))->attribute('prefix', $prefix);

                $routeRegistrar->name($locale.'.')->group(function (){
                    ($this->routesCallback)();
                });

                $this->tempPrefixes->add($prefix);
            }

            if ($this->config->getMethod() === Method::PREFIX) {
                $routeRegistrar = (new RouteRegistrar($this->router))->attribute('prefix', $locale);

                $routeRegistrar->name($locale.'.prefix.')->group(function () {
                    ($this->routesCallback)();
                });
            }
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
        if ($this->config->getUriTranslation() === false) {
            return;
        }

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
            ->map(function($segment) {
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
