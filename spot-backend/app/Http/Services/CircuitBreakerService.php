<?php

namespace App\Http\Services;

use App\Events\CircuitBreakerCoinPairSettingUpdated;
use App\Events\CircuitBreakerSettingUpdated;
use App\Models\CircuitBreakerCoinPairSetting;
use App\Models\CircuitBreakerSetting;
use App\Models\CoinSetting;
use App\Utils\BigNumber;
use Carbon\Carbon;
use App\Consts;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class CircuitBreakerService
{
    const CIRCUIT_BREAKER_CACHE_LIVE_TIME = 300; // 5 minutes
    const CIRCUIT_BREAKER_SETTING_CACHE_KEY = "CircuitBreakerSetting:current";
    const NEED_LOCK = true;
    const NO_NEED_LOCK = false;

    protected $model;
    protected $priceService;

    public function __construct()
    {
        $this->model = app(CircuitBreakerSetting::class);
        $this->priceService = new PriceService();
    }

    /**
     * ======================================
     * Circuit Breaker Setting Functions
     * ======================================
     */
    public function getSetting()
    {
        $res = $this->loadCacheSetting();
        if ($res) {
            return $res;
        }

        $res = CircuitBreakerSetting::on('master')->first();
        if (!$res) {
            $res = $this->createSetting();
        }

        return $res;
    }

    public function createSetting($saveCache = true)
    {
        // Create Default Setting
        $setting = CircuitBreakerSetting::create(config('circuit-breaker.init_data'));

        // Cache Setting If need
        if ($saveCache) {
            $this->saveCacheSetting($setting);
        }

        return $setting;
    }

    public function updateSetting($params)
    {
        $setting = $this->getSetting()->fill($params);
        $setting->save();

        // Save cache setting
        $this->saveCacheSetting($setting);

        return $setting;
    }

    public function saveCacheSetting($setting = null)
    {
        if (!$setting) {
            $setting = CircuitBreakerSetting::first();
        }

        $key = static::CIRCUIT_BREAKER_SETTING_CACHE_KEY;
        Cache::put($key, $setting, static::CIRCUIT_BREAKER_CACHE_LIVE_TIME);

        return $setting;
    }

    public function loadCacheSetting()
    {
        $key = static::CIRCUIT_BREAKER_SETTING_CACHE_KEY;
        $result = Cache::get($key);

        // If has cache, return
        if ($result) {
            return $result;
        }

        $result = CircuitBreakerSetting::first();
        Cache::put($key, $result, static::CIRCUIT_BREAKER_CACHE_LIVE_TIME);

        return $result;
    }

    public function checkExistSetting()
    {
        return CircuitBreakerSetting::exists();
    }

    /**
     * Enable/Disable Circuit Breaker
     * @param $status
     * @return mixed
     */
    public function changeStatus($status)
    {
        return $this->updateSetting(['status' => $status]);
    }


    /**
     * ======================================
     * Coin Pair Setting Functions
     * ======================================
     */
    public function createCoinPairSetting($params = [])
    {
        $coinPairSetting = new CircuitBreakerCoinPairSetting;
        $coinPairSetting->fill($params)->save();

        return $coinPairSetting;
    }

    public function createOrUpdateCoinPairSetting($params = [])
    {
        if (@$params['id']) {
            $coinPairSetting = CircuitBreakerCoinPairSetting::find($params['id']);
        } else {
            // Create New
            $coinPairSetting = new CircuitBreakerCoinPairSetting;
            if (count($params) == 0) {
                $params = config('circuit-breaker.init_data');
            }
        }

        $coinPairSetting->fill($params)->save();

        return $coinPairSetting;
    }

    public function checkAllowTradingCoinPair($currency, $coin)
    {
        if (!$currency || !$coin) {
            return true;    // block trading
        }

        // Check enable circuit setting
        $setting = $this->getSetting();

        // If circuit breaker setting is DISABLE monitor trading
        // no need block trading
        if (!$setting || $setting->status == Consts::CIRCUIT_BREAKER_STATUS_DISABLE) {
            return false;   // allow trading
        }

        $coinPair = $this->getCoinPairSetting($currency, $coin);
        if (!$coinPair) {
            return false;
        }
        if ($coinPair->block_trading == 0 || $coinPair->block_trading == null) {
            return false;
        }
        if ($coinPair->status == Consts::CIRCUIT_BREAKER_STATUS_DISABLE) {
            return false;
        }

        return $coinPair->block_trading;
    }

    public function isCircuitBreakerEnabled($currency, $coin)
    {
        if (!$currency || !$coin) {
            return true;
        }

        // Check enable circuit setting
        $setting = $this->getSetting();

        // If circuit breaker setting is DISABLE monitor trading
        // no need block trading
        if (!$setting || $setting->status == Consts::CIRCUIT_BREAKER_STATUS_DISABLE) {
            return false;
        }

        $coinPair = $this->getCoinPairSetting($currency, $coin);
        if (!$coinPair) {
            return false;
        }
        return $coinPair->status == Consts::CIRCUIT_BREAKER_STATUS_ENABLE;
    }

//    public function oldcheckAllowTradingCoinPair($currency, $coin)
//    {
//        if (!$currency || !$coin) {
//            return Consts::CIRCUIT_BREAKER_BLOCK_TRADING;
//        }
//
//        // Check enable circuit setting
//        $setting = $this->getSetting();
//
//        // If circuit breaker setting is DISABLE monitor trading
//        // no need block trading
//        if (!$setting || $setting->status == Consts::CIRCUIT_BREAKER_STATUS_DISABLE) {
//            return Consts::CIRCUIT_BREAKER_ALLOW_TRADING;
//        }
//
//        $coinPair = $this->getCoinPairSetting($currency, $coin);
//        if (!$coinPair) {
//            return Consts::CIRCUIT_BREAKER_ALLOW_TRADING;   // Default allow trading
//        }
//
//        return $coinPair->status == Consts::CIRCUIT_BREAKER_STATUS_ENABLE;
//    }

    public function checkEnableCircuitBreakerFeature()
    {
        $setting = $this->getSetting();
        if ($setting && $setting->status != Consts::CIRCUIT_BREAKER_STATUS_DISABLE) {
            return false;
        }

        return true;
    }

    public function getCoinPairCacheKey($currency, $coin)
    {
        return "CircuitBreakerCoinPairSetting:{$currency}:{$coin}:current";
    }

    public function loadCacheCoinPairSetting($currency, $coin)
    {
        $key = $this->getCoinPairCacheKey($currency, $coin);

        return Cache::get($key);
    }

    public function saveCacheCoinPairSetting($currency, $coin, $coinPair)
    {
        if (!$coinPair) {
            $coinPair = CircuitBreakerCoinPairSetting::where('currency', $currency)
                ->where('coin', $coin)
                ->first();
        }

        $key = $this->getCoinPairCacheKey($currency, $coin);

        return Cache::put($key, $coinPair, static::CIRCUIT_BREAKER_CACHE_LIVE_TIME);
    }

    public function getCoinPairSetting($currency, $coin, $onMaster = false)
    {
//        // TODO: Check load by cache
//        if ($onMaster) {
//            return CircuitBreakerCoinPairSetting::on('master')
//                ->where('currency', $currency)
//                ->where('coin', $coin)
//                ->first();
//        }
//        $coinPair = $this->loadCacheCoinPairSetting($currency, $coin);
//        if ($coinPair) {
//            return $coinPair;
//        }

        $coinPair = CircuitBreakerCoinPairSetting::on('master')
            ->where('currency', $currency)
            ->where('coin', $coin)
            ->first();

        $this->saveCacheCoinPairSetting($currency, $coin, $coinPair);

        return $coinPair;
    }

    public function buildCoinPairSettingQuery($params = [])
    {
        if (@$params['onMaster']) {
            $res = CoinSetting::on('master');
        } else {
            $res = CoinSetting::query();
        }
        $res = $res->leftJoin('circuit_breaker_coin_pair_settings', function ($join) {
                $join->on('coin_settings.currency', '=', 'circuit_breaker_coin_pair_settings.currency');
                $join->on('coin_settings.coin', '=', 'circuit_breaker_coin_pair_settings.coin');
        })
            ->when(!empty($params['currency']), function ($query) use ($params) {
                $query->where('coin_settings.currency', '=', $params['currency']);
            })
            ->select(
                'circuit_breaker_coin_pair_settings.id',
                'coin_settings.currency',
                'coin_settings.coin',
                'circuit_breaker_coin_pair_settings.range_listen_time',
                'circuit_breaker_coin_pair_settings.circuit_breaker_percent',
                'circuit_breaker_coin_pair_settings.block_time',
                'circuit_breaker_coin_pair_settings.status',
                'circuit_breaker_coin_pair_settings.locked_at',
                'circuit_breaker_coin_pair_settings.unlocked_at',
                'circuit_breaker_coin_pair_settings.last_price',
                'circuit_breaker_coin_pair_settings.last_order_transaction_id',
                'circuit_breaker_coin_pair_settings.block_trading'
            )
            ->orderBy('currency', 'asc')
            ->orderBy('coin', 'asc');

        return $res;
    }

    public function getCoinPairSettings($params)
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        return $this->buildCoinPairSettingQuery($params)->paginate($limit);
    }

    public function updateCoinPairSetting($input, $id)
    {
        $coinPair = CircuitBreakerCoinPairSetting::on('master')->find($id);
        if (!$coinPair) {
            return false;
        }

        // Update unlock time if change Coin Pair Setting.
        if ($coinPair->unlocked_at && $coinPair->locked_at) {
            // 1 hour = 3600 seconds = 3600000 milliseconds
            $input['locked_at'] = $coinPair->locked_at;
            $newBlockTime = @$input['block_time'] ?? $coinPair->block_time;
            $input['unlocked_at'] = $coinPair->locked_at + ($newBlockTime * 3600000);
        }

        $res = $coinPair->fill($input);
        $res->save();
        if ($res) {
            MasterdataService::clearCacheOneTable('circuit_breaker_coin_pair_settings');
            $this->saveCacheCoinPairSetting($coinPair->currency, $coinPair->coin, $coinPair);
            $this->sendCoinPairSettingUpdatedEvent($coinPair);
            $this->sendCircuitBreakerSettingUpdatedEvent($this->getSetting());
        }

        return $res;
    }

    public function sendCircuitBreakerSettingUpdatedEvent($data)
    {
        event(new CircuitBreakerSettingUpdated($data));
    }

    public function sendCoinPairSettingUpdatedEvent($data)
    {
        event(new CircuitBreakerCoinPairSettingUpdated($data));
    }

    /**
     * ==============================================
     * Check status Lock/Unlock when:
     * - Match Order
     * - Job check Status each minute to auto unlock
     * ==============================================
     */
    public function checkCircuitBreakerPrice($orderTransaction)
    {
        // logger('===START Check Circuit Breaker Price======>');
        // logger($orderTransaction);
        // logger('==============================>');

        $coin = $orderTransaction->coin;
        $currency = $orderTransaction->currency;

        // Get Coin Pair Status Setting
        $coinPairSetting = $this->getCoinPairSetting($currency, $coin);
        if (!$coinPairSetting) {
            // If not exist Coin Pair Setting, default is allow trading
            return true;
        }

        // logger('coinPairSettingStatus: '.json_encode($coinPairSetting->status));
        // Check price to Lock Trading
        if ($coinPairSetting->status == Consts::CIRCUIT_BREAKER_STATUS_ENABLE) {
            if ($coinPairSetting->block_trading == 1) {
                return true;
            }

            $beforePrice = $this->getBeforePriceInRange($coinPairSetting, $orderTransaction);
            $currentPrice = $orderTransaction->price;

            // logger('$beforePrice: '.$beforePrice);
            // logger('$currentPrice: '.$currentPrice);

            if ($beforePrice == null || $beforePrice == 0) {
                return true;
            }
            if ($beforePrice == null || $currentPrice == 0) {
                return true;
            }

            $needLock = $this->checkPriceFluctuations($currentPrice, $beforePrice, $coinPairSetting);

            // logger('$needLock status: ' . json_encode($needLock));
            if ($needLock == self::NEED_LOCK) {
                // logger('Need Lock NOW ! Locking Trading...');
                $this->lockCoinPairSetting($orderTransaction, $coinPairSetting);
            }

            // logger('===END Check Circuit Breaker Price======>');

            return true;
        }

        // If $coinPairSetting->status == Consts::CIRCUIT_BREAKER_STATUS_DISABLE
        // Check time to auto Unlock when over Block Time (in circuit_breaker_setting table)
        // logger('$coinPairSetting->status == Consts::CIRCUIT_BREAKER_STATUS_DISABLE');
        $this->checkAutoUnlockTrading($orderTransaction, $coinPairSetting);
        // logger('===END Check Circuit Breaker Price======>');

        return true;
    }

    public function checkAutoUnlockTrading($orderTransaction, $coinPairSetting = null)
    {
        $coin = $orderTransaction->coin;
        $currency = $orderTransaction->currency;
        if (!$coinPairSetting) {
            $coinPairSetting = $this->getCoinPairSetting($currency, $coin);
        }

        $lockedAt = $coinPairSetting->locked_at;
        $unlockedAt = $coinPairSetting->unlocked_at;
        $orderExecutedAt = $orderTransaction->created_at;

        //$unlockedAt = (Carbon::now()->timestamp * 1000) - 1;
        if (!$lockedAt || !$unlockedAt) {
            return true;
        }
        if ($orderExecutedAt < $unlockedAt) {
            // Nothing to do
            return true;
        }

        // logger('autoUpdateStatus == Unlock Setting');
        $setting = CircuitBreakerCoinPairSetting::on('master')
                        ->where('id', $coinPairSetting->id)
                        ->lockForUpdate()->first();
        $setting->update([
            'block_trading' => false,
            'locked_at' => null,
            'unlocked_at' => null,
        ]);
        // logger('Auto =====> UnLock CircuitBreakerCoinPairSetting record and Unlock Trading success. Response: '.json_encode($setting));

        MasterdataService::clearCacheOneTable('circuit_breaker_coin_pair_settings');
        $this->saveCacheCoinPairSetting($currency, $coin, $setting);
        $this->sendCoinPairSettingUpdatedEvent($setting);

        return true;
    }

    public function lockCoinPairSetting($orderTransaction, $coinPairSetting = null)
    {
        $coin = $orderTransaction->coin;
        $currency = $orderTransaction->currency;
        if (!$coinPairSetting) {
            $coinPairSetting = CircuitBreakerCoinPairSetting::on('master')
                                ->where('currency', $currency)
                                ->where('coin', $coin)->first();
        }

        // Lock Record
        $setting = CircuitBreakerCoinPairSetting::on('master')
                    ->where('id', $coinPairSetting->id)
                    ->lockForUpdate()->first();
        // Default block_time is 24h
        // And Convert to seconds
        $plusSeconds = round(($setting->block_time ?? 24) * 3600);
        $setting->fill([
            'block_trading' => true,
            'locked_at' => Carbon::now()->timestamp * 1000,
            'unlocked_at' => Carbon::now()->addSeconds($plusSeconds)->timestamp * 1000,
            'last_price' => $orderTransaction->price,
            'last_order_transaction_id' => $orderTransaction->id,
        ]);
        $setting->save();
        // logger('Lock CircuitBreakerCoinPairSetting record and lock Trading success');
        // logger('Response: ' . json_encode($setting));

        MasterdataService::clearCacheOneTable('circuit_breaker_coin_pair_settings');
        $this->saveCacheCoinPairSetting($currency, $coin, $setting);
        $this->sendCoinPairSettingUpdatedEvent($setting);
    }

    public function getBeforePriceInRange($coinPairSetting, $orderTransaction)
    {
        // logger('Setting Price===========>');
        // logger(json_encode($coinPairSetting));

        $rangeListenTime = $coinPairSetting->range_listen_time;
        $orderExecutedAt = $orderTransaction->created_at;
        $beforePrice = $this->priceService->getBeforePriceCircuitPrice($coinPairSetting, $rangeListenTime, $orderExecutedAt);

       // logger('1--$beforePrice: '.$beforePrice);
//        if (!$beforePrice) {
////            // Try get price at last block trading
////            $beforePrice = $coinPairSetting->last_price;
////            if (!$beforePrice) {
////                // Try get last price in 24 hour before
////                $beforePrice = app(PriceService::class)->getBeforePriceCircuitPrice($currency, $coin, 24, $orderExecutedAt);
////            }
////            $beforePrice = $this->priceService->getLastestPrice($currency, $coin);
           // logger('2--null $beforePrice: '.$beforePrice);
//        }

        // logger('$beforePrices: ===> ' . json_encode($beforePrice));

        return $beforePrice;
    }

    public function checkPriceFluctuations($currentPrice, $beforePrice, $coinPairSetting)
    {
        // logger('$currentPrice: '.$currentPrice);
        // logger('$beforePrice: '.$beforePrice);
        //logger(!$beforePrice);
        //logger(json_encode((BigNumber::new($beforePrice)->comp(0))));

        if (!$beforePrice || (BigNumber::new($beforePrice)->comp(0) == 0)) {
            //// abs($currentPrice) >= $coinPairSetting->circuit_breaker_percent: Break
            //// abs($currentPrice) < $coinPairSetting->circuit_breaker_percent: Nothing to do
            //if (BigNumber::new($currentPrice)->abs()->comp($coinPairSetting->circuit_breaker_percent) >= 0) {
            //    return self::NEED_LOCK;
            //}

            return self::NO_NEED_LOCK;
        }

        $circuitBreakerPercent = BigNumber::new($coinPairSetting->circuit_breaker_percent);
        $fluctationsPrice = BigNumber::new($currentPrice)->sub($beforePrice)->abs();
        $fluctationsPercent = $fluctationsPrice->div($beforePrice)->mul(100);

        // logger('checkPriceFluctuations============>');
        // logger('$currentPrice: ' . $currentPrice);
        // logger('$beforePrice: ' . $beforePrice);
        // logger('$fluctationsPrice: ' . $fluctationsPrice);
        // logger('$circuitBreakerPercent(%): ' . $circuitBreakerPercent . '%');
        // logger('$fluctationsPercent(%): ' . $fluctationsPercent . '%');

        $compareResult = BigNumber::new($fluctationsPercent)->comp($circuitBreakerPercent);

        // logger('Result compare: BigNumber::new($fluctationsPercent)->comp($circuitBreakerPercent): ' . $compareResult);
        // logger('checkPriceFluctuations=============>END');


        // If $fluctationsPercent >= $circuitBreakerPercent: Break and Lock
        // If $fluctationsPercent < $circuitBreakerPercent: Nothing to do
        if ($compareResult >= 0) {
            return self::NEED_LOCK; // Over percent. Need lock.
        }

        return self::NO_NEED_LOCK;
    }

    public function checkStatusLocking($orderTransaction, $setting = null, $coinPairSetting = null)
    {
        $coin = $orderTransaction->coin;
        $currency = $orderTransaction->currency;
        if (!$currency || !$coin) {
            return Consts::CIRCUIT_BREAKER_BLOCK_TRADING;
        }

//        $allowTrading = $this->checkAllowTradingCoinPair($currency, $coin);
//
//        if (!$setting) {
//            $setting = $this->getSetting();
//        }

        if (!$coinPairSetting) {
            $coinPairSetting = $this->getCoinPairSetting($currency, $coin);
        }


        if (!$coinPairSetting) {
            return Consts::CIRCUIT_BREAKER_ALLOW_TRADING;   // Default allow trading
        }

        if ($coinPairSetting->status == Consts::CIRCUIT_BREAKER_STATUS_DISABLE) {
            return Consts::CIRCUIT_BREAKER_BLOCK_TRADING;
        }

        return Consts::CIRCUIT_BREAKER_ALLOW_TRADING;
    }
}
