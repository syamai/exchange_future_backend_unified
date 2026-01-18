<?php

namespace App\Console\Commands;

use App\Http\Services\MemoryMonitorService;
use Illuminate\Console\Command;

class MonitorPerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:performance';

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
        $memoryMonitorService = new MemoryMonitorService();
        $memoryMonitorService->trackMemoryUsage();
        return Command::SUCCESS;
    }
}
