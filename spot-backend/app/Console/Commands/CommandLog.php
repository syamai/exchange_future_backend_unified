<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Log;

trait CommandLog
{
    public function changeLogPath()
    {
//        Log::popHandler();
//        $maxFiles = config('app.log_max_files', 5);
//        $logLevel = config('app.log_level', 'debug');
        $taskName = $this->getTaskName();
//        Log::useDailyFiles(app()->storagePath().'/logs/'.$taskName.'.log', $maxFiles, $logLevel);

        config(['logging.channels.daily' => [
            ...config('logging.channels.daily'),
            'path' => storage_path('/logs/'.$taskName.'.log'),
        ]]);

        logger(config('logging.channels.daily'));
    }

    protected function getTaskName()
    {
        $total = collect($this->argument())->reduce(function ($carry, $item) {
            return "{$carry}_{$item}";
        });
        return substr($total, 1);
    }
}
