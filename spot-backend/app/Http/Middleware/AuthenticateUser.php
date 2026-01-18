<?php

namespace App\Http\Middleware;

use App\Utils;
use Closure;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use App\Consts;
use App\Models\User;
use App\Models\UserSecuritySetting;
use Exception;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use ErrorException;

class AuthenticateUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @param string $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        Log::info("===================request===============================");
        Log::info([$request]);
        $locale = $request['lang'];
        if (Auth::check() && Auth::user()->status === Consts::USER_INACTIVE) {
            DB::table('oauth_access_tokens')->where('user_id', Auth::id())
                ->update([
                    'revoked' => Consts::TRUE,
                    'expires_at' => \Carbon\Carbon::now()
                ]);
            $accessToken = explode(' ', $request->header('Authorization'))[1] ?? null;
            if ($accessToken) {
                Utils::revokeTokensInFuture($accessToken);
            }
            throw new HttpException(422, 'account.inactive');
        } elseif ($request->has('username')) {
            $user = User::where('email', $request->username)->first();
            if ($user && $user->status !== Consts::USER_ACTIVE) {
            	if ($user->status === Consts::USER_INACTIVE) {
					$userSecuritySetting = UserSecuritySetting::where('id', $user->id)->first();
					if ($userSecuritySetting->email_verification_code) {
						throw new HttpException(422, 'validation.check_exist_email');
					}
				}

                throw new HttpException(422, 'account.inactive');
            }
        }

        return $next($request);
    }
}
