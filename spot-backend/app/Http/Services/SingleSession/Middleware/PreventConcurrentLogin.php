<?php

namespace App\Http\Services\SingleSession\Middleware;

use Illuminate\Support\Facades\Log;
use Closure;
use Illuminate\Support\Facades\Auth;
use App\Http\Services\SingleSession\SingleSessionService;

class PreventConcurrentLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (Auth::guard($guard)->check()) {
            $preventer = new SingleSessionService();
            $preventer->validateSession();
        }

        return $next($request);
    }
}
