<?php

namespace App\Http\Middleware;

use Closure;

class AuthFutureMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $authorization = $request->header('Authorization');

        $userConfig = env('FUTURE_USER');
        $passConfig = env('FUTURE_PASSWORD');

        if (!($passConfig && $userConfig)) {
            abort(500, 'Config auth empty');
        }

        if (!($authorization === 'Basic ' . base64_encode("$userConfig:$passConfig"))) {
            abort(401, 'Authentication fail');
        }

        return $next($request);
    }
}
