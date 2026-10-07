<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->cookie('locale');
        if (! in_array($locale, ['en', 'ar'], true)) {
            $locale = $request->user()?->locale ?? $request->getPreferredLanguage(['en', 'ar']) ?? 'en';
        }
        app()->setLocale($locale);

        return $next($request);
    }
}
