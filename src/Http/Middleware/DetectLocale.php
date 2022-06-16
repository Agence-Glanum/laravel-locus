<?php

namespace Glanum\Locus\Http\Middleware;

use Closure;
use Glanum\Locus\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DetectLocale
{
    public function handle(Request $request, Closure $next)
    {
        $config = app(Config::class);
        $session = $request->session();

        dd($request->server('HTTP_ACCEPT_LANGUAGE'));



        if (($locale = $session->get($config->getSessionKey()) !== null )) {
            app()->setLocale($locale);
        }

        if (Str::contains($request->getPathInfo(), $config->getLocales())) {
            $urlLocale = Str::of($request->getPathInfo())
                ->explode('/')
                ->filter(fn ($item) => $item !== '')
                ->first();

            $session->put($config->getSessionKey(), $urlLocale);

            app()->setLocale($urlLocale);
        }

        return $next($request);
    }

    public function findPreferredLanguage(Request $request)
    {
        $request->server('HTTP_ACCEPT_LANGUAGE');
    }
}
