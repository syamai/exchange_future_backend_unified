<?php

namespace App\Http\Middleware;

use Closure;

class DetechCloudFlareIp
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
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
            $request->server->set('REMOTE_ADDR', $_SERVER['HTTP_CF_CONNECTING_IP']);
        }

        return $next($request);
    }
}
