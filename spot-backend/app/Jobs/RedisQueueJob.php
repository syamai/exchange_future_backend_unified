<?php

namespace App\Jobs;

use App\Consts;
use App\Utils;
use App\Events\OrderBookUpdated;
use App\Http\Services\MasterdataService;
use App\Http\Services\OrderService;
use App\Http\Services\PriceService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RedisQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public static function dispatchIfNeed()
    {
        $reflection = new \ReflectionClass(static::class);
        $className = $reflection->getName();

        $params = func_get_args();
        $timeKey = static::getTimeKey(...$params);
        $lastRun = static::redis()->get($timeKey);
        $currentTime = static::currentMilliseconds();
        if (static::shouldAddToQueue($currentTime, $lastRun, ...$params)) {
            $nextRun = static::getNextRun($currentTime, $lastRun, ...$params);
            static::redis()->pipeline(function ($redis) use ($className, $timeKey, $nextRun, $params) {
                $redis->set($timeKey, $nextRun, 'PX', static::getUpdateInterval() * 10);
                $redis->zadd($className::getQueueName(), $nextRun, $className::serializeData(...$params));
            });
        } else {
            // Log::info('Do not add');
        }
    }

    public static function getQueueName()
    {
        $reflection = new \ReflectionClass(static::class);
        return $reflection->getShortName();
    }

    public static function getUniqueKey()
    {
        return static::getQueueName().implode('_', func_get_args());
    }

    public static function serializeData()
    {
        return json_encode(func_get_args());
    }

    protected static function shouldAddToQueue()
    {
        $currentTime = func_get_args()[0];
        $lastRun = func_get_args()[1];
        return $lastRun < $currentTime;
    }

    protected static function getNextRun()
    {
        $currencyTime = func_get_args()[0];
        $lastRun = func_get_args()[1];
        if (!$lastRun) {
            return $currencyTime;
        } else {
            return max($currencyTime, $lastRun + static::getUpdateInterval());
        }
    }

    protected static function currentMilliseconds()
    {
        return Utils::currentMilliseconds();
        // $data = static::redis()->time();
        // return $data[0] * 1000 + round($data[1] / 1000);
    }

    protected static function getTimeKey()
    {
        return static::getQueueName() . '_' . static::getUniqueKey(...func_get_args()) . '_time';
    }

    public static function getUpdateInterval()
    {
        return 500;
    }

    public static function getRedisConnection()
    {
        return Consts::RC_QUEUE;
    }

    public static function redis()
    {
        return Redis::connection(static::getRedisConnection());
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
    }
}
