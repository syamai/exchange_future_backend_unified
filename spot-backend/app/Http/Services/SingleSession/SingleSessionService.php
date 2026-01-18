<?php

namespace App\Http\Services\SingleSession;

use App\Consts;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Exceptions\UnregisteredSessionException;
use Illuminate\Support\Facades\Auth;
use App\Models\UserSession;
use Carbon\Carbon;
use BadMethodCallException;
use Laravel\Passport\Passport;
use App\Events\UserSessionRegistered;
use App\Utils\BearerToken;

class SingleSessionService
{
    const ORIGINAL_SESSION_ID_KEY = '_original_session_id';

    public static function validateSession()
    {
        if (Auth::guard()->check()) {
            if (!SingleSessionService::isValidSession()) {
                SingleSessionService::logoutUnregisteredSession(Auth::guard());
                throw new UnregisteredSessionException();
            }
        }
    }

    public static function isValidSession()
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        $sessionId = SingleSessionService::getSessionId();
        $userSession = $user->userSession;
        $isLastSession = !isset($userSession) || $userSession->session_id === $sessionId;
        $isLastSessionExpired = !isset($userSession) || Carbon::parse($userSession->expire_at)->gt(Carbon::now());
        return ($isLastSessionExpired && $isLastSession);
    }

    public static function getSessionId()
    {
        if (SingleSessionService::isPassport() && request()->bearerToken()) {
            $token = BearerToken::fromRequest();
            return $token->id;
        }
        if (SingleSessionService::isPassport() && request()->cookie(Passport::cookie())) {
            $payload = SingleSessionService::decodeJwtTokenCookie();
            return $payload['sessionId'];
        }
        return session(self::ORIGINAL_SESSION_ID_KEY, session()->getId());
    }

    public static function decodeJwtTokenCookie()
    {
        return (array) JWT::decode(
            app('encrypter')->decrypt(request()->cookie(Passport::cookie())),
            new Key(app('encrypter')->getKey(), Consts::DEFAULT_JWT_ALGORITHM)
        );
    }

    public static function logoutUnregisteredSession($driver)
    {
        if (SingleSessionService::isSession()) {
            Auth::logout();
            session()->flush();
            session()->regenerate();
        }
    }

    public static function isSession()
    {
        return SingleSessionService::getAuthDriver() === 'session';
    }

    public static function isPassport()
    {
        return SingleSessionService::getAuthDriver() === 'passport';
    }

    public static function getAuthDriver()
    {
        $currentGuard = Auth::getDefaultDriver();
        $config = config('auth.guards');
        return $config[$currentGuard]['driver'];
    }

    public static function registerSession($sessionId = null, $userId = null)
    {
        $user = Auth::user();
        $userId = $user ? $user->id : $userId;
        $sessionId = $sessionId ?? session()->getId();

        if (!$userId || !$sessionId) {
            return false;
        }
        event(new UserSessionRegistered($userId, $sessionId));
        return UserSession::on('master')->updateOrCreate(
            [
                'user_id' => $userId
            ],
            [
                'session_id' => $sessionId,
                'expire_at' => Carbon::now()->addMinutes(config('session.lifetime')),
            ]
        );
    }

    public static function unregisterSession()
    {
        $user = Auth::User();
        if (!$user) {
            return false;
        }
        return UserSession::on('master')->updateOrCreate(
            [
                'user_id' => $user->id,
                'session_id' => session()->getId(),
            ],
            [
                'expire_at' => Carbon::now(),
            ]
        );
    }

    public static function setOriginalSessionId()
    {
        if (Auth::guard('web')->check()) {
            $userSession = Auth::guard('web')->user()->userSession;
            session([self::ORIGINAL_SESSION_ID_KEY => $userSession->session_id]);
        }
    }
}
