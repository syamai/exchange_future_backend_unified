<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\MasterdataService;
use App\Models\Order;
use App\Models\Price;
use App\Models\Process;
use App\Models\User;
use App\Utils;
use App\Utils\BigNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class SpotKlineOldSymbolWorkerCommand extends SpotKlineWorkerCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:kline_old_worker_symbol {currency} {coin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create kline spot symbol';

    protected int $batchSize = 10;
    protected $sleepTime = 10;
    protected $currency;
    protected $coin;
    protected $minId;
    protected $lastRun;
    protected $checkingInterval;
    protected $redis;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->currency = $this->argument('currency') ?? '';
        $this->coin = $this->argument('coin') ?? '';
        if (!$this->currency || !$this->coin /*|| $this->coin != 'btc'*/) {
            return;
        }
        $this->checkingInterval = env('OP_CHECKING_INTERVAl_KLINE_OLD', 3000);
        $this->batchSize = env('OP_KLINE_NUMBER', 20);
        $this->sleepTime = env('OP_KLINE_SLEEP_OLD', 20);
        $this->redis = Redis::connection(static::getRedisConnection());

        $this->lastRun = Utils::currentMilliseconds();

        $this->minId = env("MIN_ID_KLINE", 0);

        $this->process = Process::firstOrCreate(['key' => $this->getProcessKey()]);
        $this->doInitJob();

        while (true) {
            if ($this->lastRun + $this->checkingInterval - 500 < Utils::currentMilliseconds()) {
                // if last matching take more than 3s to finish
                // we need end this processor, because other processor has been started
                return;
            }

            if (Utils::currentMilliseconds() - $this->lastRun > $this->checkingInterval / 2) {
                $this->lastRun = Utils::currentMilliseconds();
                $this->redis->set($this->getLastRunKey(), $this->lastRun);
            }

            $this->doJob();
            if (Utils::isTesting()) {
                break;
            }

            usleep($this->sleepTime);
        }
    }

    protected function doInitJob()
    {
        //check adn create table kline pair
        $tableName = $this->getKlineTableName($this->currency, $this->coin);
        if(!Schema::hasTable($tableName)) {
            $params = [$tableName];
            $sqlParams = implode(',', array_fill(0, sizeof($params), '?'));
            DB::connection('master')->update('CALL create_table_kline(' . $sqlParams . ')', $params);
        }

    }

    protected function doJob()
    {
        $count = 0;
        $shouldContinue = true;

        //while ($shouldContinue) {
            $this->doJobProccess($shouldContinue,$count);
        //};
    }

    protected function getProcessKey(): string
    {
        return "spot_kline_old_symbol_{$this->currency}_{$this->coin}_prices";
    }

    private function getLastRunKey(): string
    {
        return 'last_run_kline_old_symbol_' . $this->currency . '_' . $this->coin;
    }

    private static function getRedisConnection()
    {
        return Consts::RC_ORDER_PROCESSOR;
    }

    protected function log($message)
    {
        logger($message);
        $this->info($message);
    }

    protected function getNextRecords($process)
    {
        $query = Price::where('id', '>', $process->processed_id)->where('id', '<=', $this->minId)->where(['currency' => $this->currency, 'coin' => $this->coin]);
        return $query->orderBy('id', 'asc')->limit($this->batchSize)->get();
    }


}
