<?php

namespace PassportHmac\Http\Middleware;

use Closure;
use PassportHmac\Http\Services\HmacService;

class HmacTokenMiddleware
{
    protected $hmacService;

    public function __construct(HmacService $hmacService)
    {
        $this->hmacService = $hmacService;
    }

    public function handle($request, Closure $next)
    {
        if ($this->notAuth() || $this->hmacService->checking($request)) {
            return $next($request);
        }

        abort(403, 'Forbidden');
    }

    protected function notAuth()
    {
        $middleware = request()->route()->gatherMiddleware();
        return in_array('auth:api', $middleware, true) === false;
    }
}
