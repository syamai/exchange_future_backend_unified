<?php

namespace App\Http\Middleware;

use App\Consts;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Prometheus\CollectorRegistry;

class PerformanceMonitorMiddleware
{
    private $registry;

    public function __construct(CollectorRegistry $registry)
    {
        $this->registry = $registry;
    }
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (!config('monitor.prometheus.enabled')) {
            return $next($request);
        }
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $response = $next($request);

        $duration = (microtime(true) - $startTime) * 1000;
        $memory = (memory_get_usage() - $startMemory) / 1024 / 1024;

        $this->registry->getOrRegisterHistogram('app', 'http_request_duration_ms', 'HTTP request duration', [], [250, 500, 750, 1000, 1500, 2000, 5000])
            ->observe($duration);
            
        $this->registry->getOrRegisterGauge('app', 'memory_usage_mb', 'Memory usage')
            ->set($memory);

        $metrics = [
            'route' => $request->url(),
            'method' => $request->method(),
            'duration' => $duration,
            'memory' => $memory,
            'timestamp' => now()->toDateTimeString(),
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
        ];

        Redis::connection(Consts::PROMETHEUS_REDIS)->zadd(Consts::PERFORMANCE_METRICS, $startTime, json_encode($metrics));

        return $response;
    }
}
