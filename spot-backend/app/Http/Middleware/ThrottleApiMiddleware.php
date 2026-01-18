<?php
namespace App\Http\Middleware;

use App\Exceptions\TooManyRequestsException;
use App\Models\Settings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Closure;
use Illuminate\Support\Facades\Cache;

class ThrottleApiMiddleware extends ThrottleRequests
{
    const KEY_NORMAL_USER_RATE = 'api_rate_limit_normal_user_rate';
    const KEY_BOT_USER_RATE = 'api_rate_limit_bot_user_rate';
    const KEY_NOT_LOGGED_IN_USER_RATE = 'api_rate_limit_not_logged_in_user_rate';

    const CACHE_KEY_API_LIMIT_SETTING = 'api_limit_setting';
    const CACHE_TIMEOUT = 10; // seconds
    const DEFAULT_SETTING = [30, 1]; // 30 requests / 1 minute

    const except = ['margin/update-to-cross'];

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @param int|string $maxAttempts
     * @param float|int $decayMinutes
     * @return mixed
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = '')
    {
        $method = $request->method();
        $disable = env('DISABLE_THROTTLE_API', false);
        if ($disable || !in_array($method, ['POST', 'PUT', 'PATCH']) || $this->isExceptApi($request->path())) {
            return $next($request);
        }

        $userLoggedIn = $request->user();
        $setting = $this->loadSetting($userLoggedIn);
        $maxAttempts = $setting[0];
        $decayMinutes = $setting[1];

        $prefix = 'normal';
        if ($userLoggedIn) {
            $prefix = $userLoggedIn->isBot() ? 'bot' : 'normal';
        }

        $key = $prefix.$this->resolveRequestSignature($request);

        $maxAttempts = $this->resolveMaxAttempts($request, $maxAttempts);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            throw $this->buildExceptionByUser($key, $userLoggedIn, $maxAttempts, $decayMinutes);
        }

        $this->limiter->hit($key, $decayMinutes);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    private function isExceptApi($path): bool
    {
        foreach (self::except as $str) {
            if ($str == $path) {
                return true;
            }
        }
        return false;
    }

    /**
     * Resolve request signature.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     * @throws \RuntimeException
     */
    protected function resolveRequestSignature($request)
    {
        if ($user = $request->user()) {
            return sha1($user->getAuthIdentifier());
        }

        if ($route = $request->route()) {
            return sha1($route->getDomain().'|'.$request->ip());
        }

        throw new \RuntimeException(
            'Unable to generate the request signature. Route unavailable.'
        );
    }


    protected function loadSetting($userLoggedIn)
    {
        $settings = $this->loadCacheSetting();
        $setting = $settings[self::KEY_NOT_LOGGED_IN_USER_RATE];
        if ($userLoggedIn) {
            $isBot = $userLoggedIn->isBot();
            $setting = $isBot ? $settings[self::KEY_BOT_USER_RATE] : $settings[self::KEY_NORMAL_USER_RATE];
        }

        return $setting;
    }

    protected function loadCacheSetting()
    {
        $setting = Cache::get(self::CACHE_KEY_API_LIMIT_SETTING);
        if (!$setting) {
            $normalSetting = Settings::on('master')->where('key', self::KEY_NORMAL_USER_RATE)->first();
            $botSetting = Settings::on('master')->where('key', self::KEY_BOT_USER_RATE)->first();
            $notLoggedSetting = Settings::on('master')->where('key', self::KEY_NOT_LOGGED_IN_USER_RATE)->first();

            $defaultSetting = self::DEFAULT_SETTING;

            $setting = [
                self::KEY_NORMAL_USER_RATE => $normalSetting ? explode(',', $normalSetting->value) : $defaultSetting,
                self::KEY_BOT_USER_RATE => $botSetting ? explode(',', $botSetting->value) : $defaultSetting,
                self::KEY_NOT_LOGGED_IN_USER_RATE => $notLoggedSetting ? explode(',', $notLoggedSetting->value) : $defaultSetting,
            ];

            Cache::put(self::CACHE_KEY_API_LIMIT_SETTING, $setting, self::CACHE_TIMEOUT);
        }

        return $setting;
    }

    /**
     * Create a 'too many requests' exception.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @return \Exception
     */
    protected function buildExceptionByUser($key, $userLoggedIn, $maxAttempts, $decayMinutes)
    {
        $retryAfter = $this->getTimeUntilNextRetry($key);

        $headers = $this->getHeaders(
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts, $retryAfter),
            $retryAfter
        );

        $message = $this->buildMessage($maxAttempts, $decayMinutes);

        return new TooManyRequestsException(
            $message,
            $headers
        );
    }

    protected function buildMessage($maxAttempts, $decayMinutes): string
    {
        // Headers:
        // {"X-RateLimit-Limit":1,"X-RateLimit-Remaining":0,"Retry-After":1,"X-RateLimit-Reset":1588693864}
        return "Too much request weight used; current limit is $maxAttempts request weight per $decayMinutes minute. Please use the websocket for live updates to avoid polling the API.";
    }
}
