<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\CircuitBreakerService;
use App\Http\Services\MasterdataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Http\Services\HealthCheckService;

class CircuitBreakerCheckUnlockTrading extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'circuit_breaker:check_unlock_trading';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Circuit Breaker. Check and Auto Unlock Trading';

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
     * @throws \Exception
     */
    public function handle()
    {
        $healthcheck = new HealthCheckService(Consts::HEALTH_CHECK_SERVICE_CIRCUIT_BREAKER_UNLOCK, Consts::HEALTH_CHECK_DOMAIN_SPOT);
        while (true) {
            DB::beginTransaction();
            $coinPairs = $this->service->buildCoinPairSettingQuery()->get();
            $healthcheck->startLog();
            try {
                foreach ($coinPairs as $coinPair) {
                    if (!$coinPair->id) {
                        continue;
                    }
                    if ($coinPair->block_trading != Consts::CIRCUIT_BREAKER_BLOCK_TRADING_STATUS) {
                        continue;
                    }

                    // If status Consts::CIRCUIT_BREAKER_STATUS_ENABLE
                    if (!$coinPair->currency || !$coinPair->coin) {
                        continue;
                    }
                    if (!$coinPair->locked_at && !$coinPair->unlocked_at) {
                        continue;
                    }

                    $this->checkAutoUnlockTrading($coinPair);
                }
                DB::commit();
                $healthcheck->endLog();
            } catch (\Exception $ex) {
                DB::rollBack();
                $healthcheck->endLog();
                Log::error($ex);
                throw $ex;
            }
            sleep(1);
        }
    }

    private function checkAutoUnlockTrading($coinPair)
    {
        $coin = $coinPair->coin;
        $currency = $coinPair->currency;
        $onMaster = true;
        $coinPairSetting = $this->service->getCoinPairSetting($currency, $coin, $onMaster);
        $unlockedAt = $coinPairSetting->unlocked_at;
        $now = now()->timestamp * 1000; // Milliseconds

        if ($unlockedAt == null) {
            return false;
        }

        if ($now >= $unlockedAt) {
            $coinPairSetting->fill([
                'block_trading' => false,
                'locked_at' => null,
                'unlocked_at' => null,
            ]);
            $coinPairSetting->save();

            // Make cache for coin pair setting
            MasterdataService::clearCacheOneTable('circuit_breaker_coin_pair_settings');
            $this->service->saveCacheCoinPairSetting($currency, $coin, $coinPairSetting);

            // Send event coin pair setting Updated
            $this->service->sendCoinPairSettingUpdatedEvent($coinPairSetting);
        }
    }
}
