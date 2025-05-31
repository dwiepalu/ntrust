<?php

namespace Klaravel\Ntrust\Middleware;

use Closure;
use Illuminate\Http\Request;

class NtrustPermission
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $permissions
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $permissions)
    {
        if (auth()->guest() || !$request->user()->can(explode('|', $permissions))) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
