<?php

namespace App\Http\Middleware;

use App\Consts;
use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Token;

class CheckAPIKeyFromFuture
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle($request, Closure $next)
    {
        if ($request->header(Consts::API_KEY, null)) {
            $decodeKey = $this->decodeAPIKEY($request->header(Consts::API_KEY, ''));
            $accessToken = $this->regenerateJwt($decodeKey);
            $scopesToken = Token::query()->where("id", $decodeKey)->value('scopes') ?? null;
            $dataToken = Token::query()->where("id", $decodeKey)->first() ?? null;

            $request->headers->set('Authorization', 'Bearer ' . $accessToken, true);
            $request->headers->set('Scopes', $scopesToken, true);
            $request->headers->set('data', json_encode($dataToken), true);

//            $signature = request('signature');
//            if ($signature == null) {
//                abort('422', 'signature required');
//            }
        } else {
            throw new \Exception('Missing API key');
        }
        return $next($request);
    }
    private function regenerateJwt($apiKey)
    {
        $accessTokenFromCache = $this->getAccessTokenFromCache($apiKey);
        if ($accessTokenFromCache != null) {
            return $accessTokenFromCache;
        }

        $token = Token::find($apiKey);
        if (!$token) {
            return 'No token found.';
        }
        if (!($token->expires_at > Carbon::now())) {
            return 'Token expired.';
        }
        $user = User::query()->where('id', $token->user_id)->first();
        if (!$user) {
            return 'User not found';
        }
        $resultCreateToken = $user->createToken(null, $token->scopes);
        $accessTokenObj = $resultCreateToken->token;
        $accessTokenObj->secret = $token->secret;
        $accessTokenObj->expires_at = $token->expires_at;
        $accessTokenObj->save();
        Cache::put(
            $apiKey,
            $resultCreateToken->accessToken,
            Carbon::parse($token->expires_at)->diffInMinutes(Carbon::now())
        );
        return $resultCreateToken->accessToken;
    }

    private function decodeAPIKEY($apiKey)
    {
        $encrypt = Consts::API_KEY_ENCRYPTCODE;
        return strtr($apiKey, $encrypt, '0123456789abcdef');
    }

    private function getAccessTokenFromCache($key) {
        return Cache::get($key);
    }
}
