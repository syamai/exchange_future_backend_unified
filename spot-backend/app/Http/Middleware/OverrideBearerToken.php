<?php
namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Laravel\Passport\Token;
use Closure;
use App\Consts;

class OverrideBearerToken
{
    public function handle($request, Closure $next)
    {
        if ($request->header(Consts::API_KEY, null)) {
            $decodeKey = $this->decodeAPIKEY($request->header(Consts::API_KEY, ''));
            $accessToken = $this->regenerateJwt($decodeKey);
            $request->headers->set('Authorization', 'Bearer ' . $accessToken, true);

            $signature = $request->header(Consts::SIGNATURE_HEADER, null);
            if ($signature == null) {
                abort('422', 'signature required');
            }
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
        $accessTokenObj->expires_at = $token->expires_at;
        $accessTokenObj->secret = $token->secret;
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
