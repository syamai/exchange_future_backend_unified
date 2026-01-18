<?php

namespace App\Providers;

use App\Http\Middleware\AuthenticateUser;
use App\Models\UserWithdrawalAddress;
use App\Policies\UserWithdrawalAddressPolicy;
use Laravel\Passport\Passport;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
        UserWithdrawalAddress::class => UserWithdrawalAddressPolicy::class
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Passport::routes(null, [
            'prefix' => 'api/v1/oauth'
        ]);

        // Token expired in 1day = 24x60 = 1440 minutes
        $tokenExpireTime = env('TOKEN_EXPIRE_TIME', 1440);
        Passport::tokensExpireIn(now()->addMinutes($tokenExpireTime));
    }
}
