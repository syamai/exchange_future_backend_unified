<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\ExchangeBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExchangeBalanceController extends AppBaseController
{
    protected $takeProfit;
    public function __construct(ExchangeBalanceService $takeProfit)
    {
        $this->takeProfit = $takeProfit;
    }

    public function getAllCoins()
    {
        try {
            $coinsDB = DB::table('coin_settings')->pluck('coin')->unique()->toArray();
            return $this->sendResponse($coinsDB, '200');
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getHeaderTableName()
    {
        try {
            $result = $this->takeProfit->getHeaderTableName();
            return $this->sendResponse($result, '200');
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getExchangeBalanceDetail(Request $request)
    {
        $input = $request;
        try {
            $result = $this->takeProfit->getProfitBalance($input);
            return $this->sendResponse($result);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }
}
