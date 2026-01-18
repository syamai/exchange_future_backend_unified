<?php

namespace App\Http\Middleware;

use App\Consts;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class IsPartnerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if(is_null($user->is_partner)) {
            throw new UnprocessableEntityHttpException('exception.partner_not_found');
        }

        if($user->is_partner == Consts::PARTNER_INACTIVE) {
            throw new UnprocessableEntityHttpException('exception.partner_is_inactive');
        }
        return $next($request);
    }
}
