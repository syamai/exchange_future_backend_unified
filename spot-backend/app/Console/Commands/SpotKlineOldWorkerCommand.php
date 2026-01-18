<?php

namespace App\Console\Commands;

use App\Http\Services\MasterdataService;
use App\Models\Order;
use App\Models\Price;
use App\Models\Process;
use App\Models\User;
use App\Utils;
use App\Utils\BigNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpotKlineOldWorkerCommand extends SpotKlineWorkerCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:kline_worker_old';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create kline spot old';

    protected int $batchSize = 1000;
    protected $sleepTime = 10;


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->process = Process::firstOrCreate(['key' => $this->getProcessKey()]);
        $this->doInitJob();

        while (true) {
            $this->doJob();
            usleep($this->sleepTime);
        }
    }

    protected function doJob()
    {
        $count = 0;
        $shouldContinue = true;

        while ($shouldContinue) {
            $this->doJobProccess($shouldContinue,$count);
        };
    }

    protected function getProcessKey(): string
    {
        return 'spot_kline_old_prices';
    }

    protected function log($message)
    {
        logger($message);
        $this->info($message);
    }

    protected function getNextRecords($process)
    {
        $query = Price::where('id', '<', $process->processed_id)->where('id', '>', 3361559);
        return $query->orderBy('id', 'desc')->limit($this->batchSize)->get();
    }


}
