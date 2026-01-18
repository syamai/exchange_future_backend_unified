<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use App\Consts;
use Illuminate\Support\Facades\Log;
use Exception;

class CoinCheckAPIController extends AppBaseController
{

    public function getPriceBtcUsdExchanges(Request $request)
    {
        $data = [];
        if (Cache::has(Consts::CACHE_KEY_CC_BTC_USD)) {
            $data = Cache::get(Consts::CACHE_KEY_CC_BTC_USD);
        }
        return $this->sendResponse($data);
    }
}
