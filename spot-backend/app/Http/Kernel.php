<?php

namespace App\Http;

use App\Http\Middleware\AuthFutureMiddleware;
use App\Http\Middleware\DecryptParamsMiddleware;
use App\Utils;
use App\Http\Middleware\AuthWebhookMiddleware;

use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Routing\Pipeline;
use Illuminate\Support\Facades\Facade;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use PassportHmac\Http\Middleware\HmacTokenMiddleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        HandleCors::class,
        \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \App\Http\Middleware\TrustProxies::class,
        \App\Http\Middleware\DetechCloudFlareIp::class,
        \App\Http\Middleware\PerformanceMonitorMiddleware::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Services\SingleSession\Middleware\CreateFreshApiToken::class,
            \App\Http\Middleware\Language::class,
            \App\Http\Middleware\Referral::class,
            // \App\Http\Services\SingleSession\Middleware\PreventConcurrentLogin::class,
        ],

        'api' => [
            DecryptParamsMiddleware::class,
            \App\Http\Middleware\OverrideBearerToken::class,
//            'throttle_requests_per_second:200000,86400',
            'bindings',
            \App\Http\Middleware\AuthenticateUser::class,
            'throttle_api',
            // \App\Http\Services\SingleSession\Middleware\PreventConcurrentLogin::class,
            \App\Http\Middleware\Language::class,
            \App\Http\Middleware\ManageDevice::class,
            HmacTokenMiddleware::class
        ],

        'api_webview' => [
            \App\Http\Middleware\OverrideBearerToken::class,
            'bindings',
            \App\Http\Middleware\AuthenticateUser::class,
            'throttle_api',
            \App\Http\Middleware\Language::class,
            \App\Http\Middleware\ManageDeviceWebView::class,
            HmacTokenMiddleware::class
        ],

        'admin' => [
//            \App\Http\Middleware\EncryptCookies::class,
//            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
//            \Illuminate\Session\Middleware\StartSession::class,
//            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
//            \App\Http\Middleware\VerifyCsrfToken::class,
            EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\AdminLanguage::class,
        ],

        'metric' => [],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'throttle_api' => \App\Http\Middleware\ThrottleApiMiddleware::class,
        'throttle_requests_per_second' => \App\Http\Middleware\CustomThrottleRequests::class,
        'auth.admin' => \App\Http\Middleware\AuthenticateAdmin::class,
        'admin.guest' => \App\Http\Middleware\RedirectIfAdminAuthenticated::class,
        'auth.message' => \App\Http\Middleware\AuthenticateMessage::class,
        'encrypt_pass' => \App\Http\Middleware\EncryptPassword::class,
        'auth.webhook' => AuthWebhookMiddleware::class,
        'auth.future' => AuthFutureMiddleware::class,
        'scopes' => \Laravel\Passport\Http\Middleware\CheckScopes::class,
        'scope' => \Laravel\Passport\Http\Middleware\CheckForAnyScope::class,
        'block_before_mam_api' => \App\Http\Middleware\BlockBeforeAPIMAM::class,
        'block_after_mam_api' => \App\Http\Middleware\BlockAfterAPIMAM::class,
        'pre_login' => \App\Http\Middleware\PreLoginMiddleware::class,
        'suf_login' => \App\Http\Middleware\SufLoginMiddleware::class,
        'check_api_key' => \App\Http\Middleware\CheckAPIKeyFromFuture::class,
        'decrypt_params' => \App\Http\Middleware\DecryptParamsMiddleware::class,
        'is_partner' => \App\Http\Middleware\IsPartnerMiddleware::class,
        'partner_login' => \App\Http\Middleware\PartnerLoginMiddleware::class,
        'telescope_login' => \App\Http\Middleware\CheckTelescopeLogin::class,
    ];

    /**
     * Handle an incoming HTTP request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function handle($request)
    {
        try {
            $request->enableHttpMethodParameterOverride();

            $response = $this->sendRequestThroughRouter($request);
        } catch (\Exception $e) {
            $this->reportException($e);

            $response = $this->renderException($request, $e);
        }

        $this->app['events']->dispatch(
            new RequestHandled($request, $response)
        );

        $runtime = 0;
        $startTime = session('__start_time__');
        if ($startTime) {
            $runtime = Utils::currentMilliseconds() - $startTime;
        }
        logger('End session ' . session()->getId() . ' ' . $runtime);
        return $response;
    }

    /**
     * Send the given request through the middleware / router.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    protected function sendRequestThroughRouter($request)
    {
        $this->app->instance('request', $request);

        Facade::clearResolvedInstance('request');

        $this->bootstrap();

        logger('Start session ' . session()->getId());
        session(['__start_time__' => Utils::currentMilliseconds()]);

        return (new Pipeline($this->app))
                    ->send($request)
                    ->through($this->app->shouldSkipMiddleware() ? [] : $this->middleware)
                    ->then($this->dispatchToRouter());
    }
}
