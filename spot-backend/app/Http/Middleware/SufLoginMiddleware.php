<?php

namespace App\Http\Middleware;

use App\Consts;
use App\Http\Services\Auth\DeviceService;
use App\Http\Services\UserService;
use App\Jobs\SendDataToFutureEvent;
use App\Models\User;
use App\Utils;
use App\Utils\BearerToken;
use Carbon\Carbon;
use Closure;
use ErrorException;
use Exception;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use League\OAuth2\Server\Exception\OAuthServerException;

class SufLoginMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse) $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (!empty($response->exception)) {
            return $response;
        }
        $requestData = request();
        $locale = $requestData->get('lang');
        $user = DB::table('users')->where('email', $requestData->get('username'))->first();
        if ($user) {
            // user first login
            if (!$user->last_login_at) {
                SendDataToFutureEvent::dispatch('login', $user->id);
            }
            DB::table('users')
            ->where('id', $user->id)
            ->update(['last_login_at' => round(microtime(true) * 1000)]);
            
            $userId = $user->id;
            app(UserService::class)->updateOrCreateUserLocaleWhenLogin($locale, $userId);
            $this->extendTokenExpire($user);
        }
        return $this->authenticated($response);
    }

    protected function authenticated($response)
    {
        $request = request();
        $user = $request->userInformation;

        $userSecuritySettings = $request->userSecuritySettings;

        $params = $request->input();
        $locale = $request->get('lang');
        $deviceService = new DeviceService();

        if ($user->type !== 'bot' && $userSecuritySettings->otp_verified) {
            $deviceService->checkValid($user, $request->ip(), false);
            $result = $this->verifyOtp($user, $params);
            if (!$result) {
                throw new OAuthServerException(__('validation.otp_incorrect', [], $locale), 6, 'invalid_otp');
            }
            if ($result === 409) {
                throw new OAuthServerException(__('validation.otp_not_used', [], $locale), 6, 'invalid_otp');
            }
        }
        $deviceService->checkValid($user, $request->ip());

        return $this->modifyResponse($response);
    }

    protected function modifyResponse($response)
    {
        $content = $this->getResponseContent($response);
        $content['secret'] = $this->createTokenSecret($content);
        $content['locale'] = app()->getLocale();
        $redirectUrl = $this->attemptSiteSupport();
        if ($redirectUrl) {
            $content['redirectUrl'] = $redirectUrl;
        }

        // sync access token to future account when user login spot success
        $this->syncAccessToken($response);

        return $response->setContent($content);
    }

    protected function getResponseContent($response)
    {
        return collect(json_decode($response->content()));
    }

    protected function createTokenSecret($content)
    {
        $token = BearerToken::fromJWT($content['access_token']);
        $token->secret = Str::random(40);
        $token->save();
        return $token->secret;
    }

    private function attemptSiteSupport()
    {
        $request = request();
        $key = config('app.zendesk_key');

        if (!$request->has('redirectUrl') || empty($key)) {
            return null;
        }
        $domain = Consts::DOMAIN_SUPPORT;
        $redirectUrl = $request->get('redirectUrl');
        if (strpos($redirectUrl, $domain) === false) {
            return $redirectUrl;
        }
        $now = time();
        $token = array(
            'jti' => md5($now . rand()),
            'iat' => $now,
            'name' => $request->get('username'),
            'email' => $request->get('username')
        );
        $jwt = JWT::encode($token, $key, Consts::DEFAULT_JWT_ALGORITHM);
        return "{$domain}/access/jwt?jwt={$jwt}&$redirectUrl";
    }

    public function extendTokenExpire($user)
    {
        if (@$user->type != 'bot') {
            return true;
        }

        $extendMonths = 12;
        $oauthId = @DB::table('oauth_access_tokens')->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first()->id;
        if ($oauthId) {
            $refreshTokenRecord = DB::table('oauth_refresh_tokens')->where('access_token_id', $oauthId)->first();
            if ($refreshTokenRecord) {
                $expiresAt = Carbon::createFromFormat('Y-m-d H:i:s', $refreshTokenRecord->expires_at);
                $expiresAt->addMonths($extendMonths);

                DB::table('oauth_access_tokens')->where('id', $oauthId)->update([
                    'expires_at' => $expiresAt->format('Y-m-d H:i:s')
                ]);

                DB::table('oauth_refresh_tokens')->where('access_token_id', $oauthId)->update([
                    'expires_at' => $expiresAt->format('Y-m-d H:i:s')
                ]);
            }
        }
    }

    protected function verifyOtp($user, $params)
    {
        if (array_key_exists('otp', $params)) {
            $otp = $params['otp'];
            return $user->verifyOtp($otp);
        }
        return false;
    }

    protected function syncAccessToken($response)
    {
        $token = json_decode($response->getContent())->access_token;
        Utils::syncAccessToken($token);
    }
}
