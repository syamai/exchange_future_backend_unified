<?php

namespace App\Http\Services\SingleSession\Middleware;

use Laravel\Passport\Http\Middleware\CreateFreshApiToken as BaseCreateFreshApiToken;
use App\Http\Services\SingleSession\ApiTokenCookieFactory;

class CreateFreshApiToken extends BaseCreateFreshApiToken
{
    public function __construct(ApiTokenCookieFactory $cookieFactory)
    {
        $this->cookieFactory = $cookieFactory;
    }
}
