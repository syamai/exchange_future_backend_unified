<?php

namespace App\Console\Commands;

use App\Consts;
use App\Utils;
use App\Jobs\ProcessOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class ProcessRedisJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis-worker:process {channel}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private $sleepTime = 100000;

    private $lastLogTime = 0;



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $channel = $this->argument('channel');
        while (true) {
            $data = static::redis()->zrange($channel, 0, 0, 'WITHSCORES');
            $keys = array_keys($data);
            if ($keys && count($keys) > 0) {
                $key = $keys[0];
                $time = static::currentMilliseconds();
                if ($data[$key] < $time) {
                    echo('Processing data: ' . $key . ", at: $time\n");
                    static::redis()->zrem($channel, $key);
                    $fullName = 'App\\Jobs\\' . $channel;
                    $fullName::dispatch($key)->onConnection(Consts::CONNECTION_SOCKET);
                    continue;
                }
            }

            $currentTime = Utils::currentMilliseconds();
            if ($currentTime - $this->lastLogTime > 10000) {
                $this->lastLogTime = $currentTime;
                echo("Processing data: none, at: $currentTime\n");
            }
            usleep($this->sleepTime);
        }
    }

    private static function currentMilliseconds()
    {
        $data = static::redis()->time();
        return $data[0] * 1000 + round($data[1] / 1000);
    }

    public static function getRedisConnection()
    {
        return Consts::RC_QUEUE;
    }

    public static function redis()
    {
        return Redis::connection(static::getRedisConnection());
    }
}
