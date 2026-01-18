<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\AppBaseController;
use App\Models\CoinMarketCapTicker;
use App\Http\Services\MasterdataService;
use App\Http\Services\CoinMarketCapService;
use Illuminate\Support\Facades\DB;


use App\Consts;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Class CoinMarketCapAPIController
 * @package App\Http\Controllers\API
 */
class CoinMarketCapAPIController extends AppBaseController
{
    /**
     * @var CoinMarketCapService
     */
    private CoinMarketCapService $coinMarketCapService;

    /**
     * CoinMarketCapAPIController constructor.
     */
    public function __construct()
    {
        $this->coinMarketCapService = new CoinMarketCapService();
    }


    public function getCurrentRate(): JsonResponse
    {
        try {
            $settings = MasterdataService::getOneTable('settings')
                ->filter(function ($value) {
                    return $value->key === Consts::SETTING_CURRENCY_COUNTRY;
                })
                ->first();
            $result = [];
            if ($settings) {
                $key = Consts::CACHE_KEY_CMC_TICKER_CURRENCY . $settings->value;
                if (Cache::has($key)) {
                    $result = Cache::get($key);
                }
            }
            return $this->sendResponse($result);
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e);
        }
    }

    /**
     * @return JsonResponse
     */
    public function getCurrentCurrenciesRate(): JsonResponse
    {
        DB::beginTransaction();
        try {
            $data = $this->coinMarketCapService->getCurrentCurrenciesRate();
            DB::commit();
            return $this->sendResponse($data);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->sendError($e);
        }
    }

    /**
     * @return JsonResponse
     */
    public function getForSalePoint(): JsonResponse
    {
        $data = CoinMarketCapTicker::whereIn('symbol', ['BTC', 'ETH', 'USDT'])->get();
        return $this->sendResponse($data);
    }
}
