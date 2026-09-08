<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['fa', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? config('app.locale', 'fa');

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = config('app.fallback_locale', 'en');
        }

        App::setLocale($locale);
        view()->share('htmlLocale', $locale);
        view()->share('htmlDir', $locale === 'fa' ? 'rtl' : 'ltr');

        return $next($request);
    }
}
