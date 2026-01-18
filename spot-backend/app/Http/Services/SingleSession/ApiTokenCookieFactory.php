<?php

namespace App\Http\Services\SingleSession;

use App\Consts;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Laravel\Passport\ApiTokenCookieFactory as BaseApiTokenCookieFactory;

class ApiTokenCookieFactory extends BaseApiTokenCookieFactory
{
    /**
     * Create a new JWT token for the given user ID and CSRF token.
     *
     * @param  mixed  $userId
     * @param  string  $csrfToken
     * @param  \Carbon\Carbon  $expiration
     * @return string
     */
    protected function createToken($userId, $csrfToken, Carbon $expiration)
    {
        return JWT::encode([
            'sessionId' => SingleSessionService::getSessionId(),
            'sub' => $userId,
            'csrf' => $csrfToken,
            'expiry' => $expiration->getTimestamp(),
        ], $this->encrypter->getKey(), Consts::DEFAULT_JWT_ALGORITHM);
    }
}
