<?php

namespace App\Console\Commands;

use App\Consts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Prometheus\CollectorRegistry;

class ResetPrometheusMetrics extends Command
{
    private $collectorRegistry;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prometheus:reset-metrics';

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

    public function __construct(CollectorRegistry $collectorRegistry)
    {
        parent::__construct();
        $this->collectorRegistry = $collectorRegistry;
    }

    public function handle()
    {

        $this->collectorRegistry->wipeStorage();

        Redis::connection(Consts::PROMETHEUS_REDIS)->del(Consts::PERFORMANCE_METRICS);
        
        $this->info('All Prometheus metrics have been reset.');
    }
}
