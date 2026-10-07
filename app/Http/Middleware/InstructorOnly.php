<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InstructorOnly
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->isInstructor(), 403);

        return $next($request);
    }
}
