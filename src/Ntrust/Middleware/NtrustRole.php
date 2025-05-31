<?php

namespace Klaravel\Ntrust\Middleware;

use Closure;
use Illuminate\Http\Request;

class NtrustRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Request $request
     * @param  Closure $next
     * @param  string $roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $roles)
    {
        if (auth()->guest() || !$request->user()->hasRole(explode('|', $roles))) {
            abort(403, 'Unauthorized.');
        }
        return $next($request);
    }
}
