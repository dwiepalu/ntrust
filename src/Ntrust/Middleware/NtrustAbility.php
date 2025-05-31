<?php 

namespace Klaravel\Ntrust\Middleware;

use Closure;
use Illuminate\Http\Request;

class NtrustAbility
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $roles
     * @param string $permissions
     * @param string|bool $validateAll
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $roles, $permissions, $validateAll = 'false')
    {
        // Konversi string ke boolean, karena middleware menerima parameter string
        $validateAll = filter_var($validateAll, FILTER_VALIDATE_BOOLEAN);

        if (auth()->guest() || !$request->user()->ability(
            explode('|', $roles), 
            explode('|', $permissions), 
            ['validate_all' => $validateAll]
        )) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
