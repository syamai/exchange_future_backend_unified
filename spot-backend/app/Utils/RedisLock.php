<?php

namespace App\Utils;

use Illuminate\Support\Facades\Redis;

class RedisLock
{
    const ROUND_MODE_FLOOR = 'floor';
    const ROUND_MODE_CEIL = 'ceil';
    const ROUND_MODE_HALF_UP = 'half_up';

    protected $precision = 10;

    protected $value;

    public static function lock($key, $value, $time)
    {
        return !!Redis::set($key, $value, 'NX', 'PX', $time);
    }

    public static function extend($key, $value, $time)
    {
        $script = '
            if redis.call("get",ARGV[1]) == ARGV[2] then
                return redis.call("psetex",ARGV[1], ARGV[3], ARGV[2])
            else
                return 0
            end
        ';
        return !!Redis::eval($script, 0, $key, $value, $time);
    }

    public static function unlock($key, $value)
    {
        $script = '
            if redis.call("get",ARGV[1]) == ARGV[2] then
                return redis.call("del",ARGV[1])
            else
                return 0
            end
        ';
        return !!Redis::eval($script, 0, $key, $value);
    }
}
