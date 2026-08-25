<?php

namespace App\Http\Middleware;

use App\Foundation\Localization\SupportedLocales;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class SetApplicationLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $locale = $user instanceof User ? $user->language_code : config('app.locale');
        $supported = SupportedLocales::all();

        app()->setLocale(in_array($locale, $supported, true) ? $locale : config('app.fallback_locale'));

        return $next($request);
    }
}
