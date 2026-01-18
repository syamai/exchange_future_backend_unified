<?php

namespace App\Console\Commands;

use App\Consts;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;

class MonitorCleanUp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $clearDays = 2; 
        $threshold = now()->subDays($clearDays)->timestamp;

        Redis::connection(Consts::PROMETHEUS_REDIS)->zremrangebyscore(Consts::MEMORY_METRICS, '-inf', $threshold);
        Redis::connection(Consts::PROMETHEUS_REDIS)->zremrangebyscore(Consts::PERFORMANCE_METRICS, '-inf', $threshold);

        $this->clearSlowQueryLog($clearDays);

        return Command::SUCCESS;
    }

    public function clearSlowQueryLog($clearDays) {
        $logPath = storage_path('logs/slowquery');
        $files = File::files($logPath);

        $now = now();

        foreach ($files as $file) {
            $lastModified = filemtime($file);

            if ($now->diffInDays(Carbon::createFromTimestamp($lastModified)) > $clearDays) {
                File::delete($file);
            }
        }
    }
}
