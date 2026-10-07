<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            return $request->expectsJson() ? response()->json(['message' => __('ui.login_required')], 401) : redirect()->guest(route('login'));
        }
        if ($user->status !== 'active' || $request->session()->get('auth_version') !== $user->session_version || ($user->must_change_password && $user->temporary_password_expires_at?->isPast())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson() ? response()->json(['message' => __('ui.session_ended')], 401) : redirect()->route('login')->with('status_key', 'session_ended');
        }
        if ($user->must_change_password && ! $request->routeIs('profile', 'profile.password', 'logout', 'locale')) {
            return $request->expectsJson() ? response()->json(['message' => __('ui.change_password_first')], 403) : redirect()->route('profile');
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'same-origin');

        return $response;
    }
}
