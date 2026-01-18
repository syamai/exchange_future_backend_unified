<?php

namespace App\Http\Middleware;

use Closure;
use App\Utils;

class EncryptPassword
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
        //$request['password'] = Utils::encrypt($request['password']);
        return $next($request);
    }
}
