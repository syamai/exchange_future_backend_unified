<?php

namespace App\Http\Middleware;

use Closure;
use App\Service\Margin\Utils;

class BlockAfterAPIMAM
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        Utils::finishMamProcess();

        return $response;
    }
}
