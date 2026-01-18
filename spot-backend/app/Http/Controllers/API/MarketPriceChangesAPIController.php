<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use Illuminate\Support\Facades\Redis;
use App\Consts;

class MarketPriceChangesAPIController extends AppBaseController
{
    public function getPriceChanges()
    {
        $result = [];
        $sources = array_filter(Redis::mGet(Consts::SUPPORTED_EXCHANGES));
        foreach ($sources as $source) {
            $result = array_merge($result, json_decode($source));
        }
        return $this->sendResponse($result);
    }
}
