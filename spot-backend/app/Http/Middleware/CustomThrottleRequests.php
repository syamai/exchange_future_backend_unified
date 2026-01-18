<?php


namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Closure;

class CustomThrottleRequests extends ThrottleRequests
{

    /**
     * Handle an incoming request.
     * TODO: cần xem lại để sửa cho chuẩn
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @param int $maxAttempts
     * @param int $decaySeconds
     * @param string $prefix
     * @return mixed
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decaySeconds = 1, $prefix = '')
    {
        // if bot, no throttle
        $user = $request->user();
        if ($user && $user->isBot()) {
            return $next($request);
        }

        $decayMinutes = $decaySeconds / 60;
        $key = $this->resolveRequestSignature($request) . "|{$maxAttempts}|{$decaySeconds}";

        $maxAttempts = $this->resolveMaxAttempts($request, $maxAttempts);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $this->logUserThrottle($request, 'blocked');
            /**
             * Thêm $maxAttempts
             */
            throw $this->buildException($request, $key, $maxAttempts, 10);
        }
        $this->logUserThrottle($request, 'pass');

        $this->limiter->hit($key, $decayMinutes);
        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    private function logUserThrottle($request, $status)
    {
        $user = $request->user();
        if (!$user) {
            return;
        }

        $path = $request->path();

        logger("CustomThrottleRequestsLog $path $status {$user->getAuthIdentifier()}");
    }
}
