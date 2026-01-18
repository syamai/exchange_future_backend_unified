<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\CircuitBreakerService;
use App\Models\OrderTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Http\Services\HealthCheckService;

class CircuitBreakerCheckPriceProcess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'circuit_breaker:check_price_process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Circuit Breaker. Check every second to detech fluctuation price';

    protected $service;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->service = new CircuitBreakerService();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $healthcheck = new HealthCheckService(Consts::HEALTH_CHECK_SERVICE_CIRCUIT_BREAKER_LOCK, Consts::HEALTH_CHECK_DOMAIN_SPOT);
        while (true) {
            DB::beginTransaction();
            $healthcheck->startLog();
            try {
                $this->checkHandle();
                sleep(1);

                DB::commit();
                $healthcheck->endLog();
            } catch (\Exception $ex) {
                DB::rollBack();
                $healthcheck->endLog();
                Log::error($ex);
                throw $ex;
            }
        }
    }

    private function checkHandle()
    {
        $coinPairs = $this->service->buildCoinPairSettingQuery(['onMaster' => true])->get();

        // logger('START Check CIRCUIT-BREAKER=============>');

        $setting = $this->service->getSetting();
        if ($setting->status != Consts::CIRCUIT_BREAKER_STATUS_ENABLE) {
            return true;
        }

        foreach ($coinPairs as $coinPair) {
            if (!$coinPair->id) {
                continue;
            }

            if ($coinPair->status != Consts::CIRCUIT_BREAKER_STATUS_DISABLE) {
                // If status == Consts::CIRCUIT_BREAKER_STATUS_ENABLE
                if (!$coinPair->currency || !$coinPair->coin) {
                    continue;
                }

                $this->checkFluctuationPrice($coinPair);
            }
        }
    }

    private function checkFluctuationPrice($coinPair)
    {
        $coin = $coinPair->coin;
        $currency = $coinPair->currency;
        $loadOnMaster = true;
        $now = now()->timestamp * 1000; // Milliseconds
        $rangeListenTime = $coinPair->range_listen_time ?? 0;
        $endTime = $now - ($rangeListenTime * 3600000); // 1 hour = 3600 seconds = 3600000 milliseconds
        $coinPairSetting = $this->service->getCoinPairSetting($currency, $coin, $loadOnMaster);
        if ($coinPairSetting->last_order_transaction_id) {
            if ($coinPairSetting->unlocked_at) {
                $lastOrder = OrderTransaction::on('master')->find($coinPairSetting->last_order_transaction_id);
            } else {
                $lastOrder = OrderTransaction::on('master')
                    ->where('currency', $currency)
                    ->where('coin', $coin)
                    ->where('created_at', '<=', $now)
                    ->where('created_at', '>=', $endTime)
                    ->orderBy('id', 'desc')
                    ->where('id', '>', $coinPairSetting->last_order_transaction_id)
                    ->first();
            }
        } else {
            $lastOrder = OrderTransaction::query()
                ->where('currency', $currency)
                ->where('coin', $coin)
                ->where('created_at', '<=', $now)
                ->where('created_at', '>=', $endTime)
                ->orderBy('id', 'desc')
                ->first();
        }

        if ($lastOrder) {
            $this->service->checkCircuitBreakerPrice($lastOrder);
        }
    }
}
