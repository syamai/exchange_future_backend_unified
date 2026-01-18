<?php

namespace PassportHmac;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use PassportHmac\Http\Middleware\HmacTokenMiddleware;

class PassportHmacServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../routers/api.php');

        Passport::tokensCan(Define::TOKENS_CAN);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        app('router')->aliasMiddleware('hmac-token', HmacTokenMiddleware::class);
    }
}
