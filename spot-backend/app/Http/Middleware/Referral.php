<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Session;
use Closure;
use App\Models\User;

class Referral
{
    public function handle($request, Closure $next)
    {
        if ($request->has('ref')) {
            $referralCode = $request->input('ref');
            if (User::where('referrer_code', $referralCode)->exists()) {
                Session::put('referrer_code', $referralCode);
            }
        }
        return $next($request);
    }
}
