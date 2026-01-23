<?php

namespace App\Providers;

use DB;
use Log;
use Illuminate\Support\ServiceProvider;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

class PrometheusServiceProvider extends ServiceProvider
{
    private $registry;
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Skip Redis connection if Prometheus is disabled
        if (!config('monitor.prometheus.enabled')) {
            $this->app->singleton(CollectorRegistry::class, function(){
                return new CollectorRegistry(new \Prometheus\Storage\InMemory());
            });
            return;
        }

        $this->app->singleton(CollectorRegistry::class, function(){
            $redisConfig = config('database.redis.prometheus');
            $adapter = new Redis([
                'host' => $redisConfig['host'],
                'port' => $redisConfig['port'],
                'password' => $redisConfig['password'],
                'database' => $redisConfig['database'],
                'timeout' => 0.1,
                'read_timeout' => 10,
                'persistent_connections' => false,
            ]);
            return new CollectorRegistry($adapter);
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        if (!config('monitor.prometheus.enabled')) {
            return;
        }
        $this->registry = $this->app->make(CollectorRegistry::class);
        DB::listen(function ($query) {
            $time = $query->time;

            // Check for slow queries (100ms threshold)
            if ($time > 100) {
                Log::channel('slow-query')->info('Slow Query Detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $time,
                ]);

                // Record to Prometheus
                $this->registry->getOrRegisterHistogram('app', 'db_query_duration_ms', 'Database query duration', [], [50, 100, 200, 300, 500, 1000])
                    ->observe($time);
            }
        });
    }
}
