<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\MasterdataService;
use App\Http\Services\PriceService;
use App\Utils\BigNumber;
use Illuminate\Support\Facades\DB;
use Knuckles\Scribe\Attributes\QueryParam;
use stdClass;

/**
 * @subgroup Chart
 *
 * @authenticated
 */
class ChartAPIController extends AppBaseController
{

    protected $priceService;

    public function __construct()
    {
        $this->priceService = new PriceService();
    }

    public function getConfig()
    {
        $config = [];
        $config['supports_search']          = true;
        $config['supports_group_request']   = false;
        $config['supports_marks']           = false;
        $config['supports_timescale_marks'] = false;
        $config['supports_time']            = true;
        $config['supported_resolutions']    = ["1","3","5","15","30","60","120","240", "360", "720", "D", "W","M"];
        return $config;
    }

    public function getServerTime()
    {
        return time();
    }

    /**
     * Chart
     *
     * @response {
    [
        {
        "low": "7.0000000000",
        "high": "7.0000000000",
        "open": "7.0000000000",
        "close": "7.0000000000",
        "volume": "0",
        "time": 1684983600000,
        "opening_time": 1684983600000,
        "closing_time": 1684983600000
        },
        {
        "low": "7.0000000000",
        "high": "7.0000000000",
        "open": "7.0000000000",
        "close": "7.0000000000",
        "volume": "0",
        "time": 1684987200000,
        "opening_time": 1684987200000,
        "closing_time": 1684987200000
        },
        {
        "low": "7.0000000000",
        "high": "7.0000000000",
        "open": "7.0000000000",
        "close": "7.0000000000",
        "volume": "0",
        "time": 1684990800000,
        "opening_time": 1684990800000,
        "closing_time": 1684990800000
        },
    ]
     * }
     */
    #[QueryParam("currency", "string", "currency. ", required: true, example: 'usdt')]
    #[QueryParam("coin", "string", "coin. ", required: true, example: 'bnb')]
    #[QueryParam("resolution", "integer", "resolution. ", required: false, example: 3600000)]
    #[QueryParam("from", "integer", "start date. ", required: false, example: 1684984550)]
    #[QueryParam("to", "integer", "end date. ", required: false, example: 1690168610)]
    public function getBars(Request $request)
    {
        $showChartNew = env("SHOW_CHART_TABLE_SYMBOL_SPOT", false);
        if ($showChartNew) {
            return $this->priceService->getBarsNew($request->all());
        }
        return $this->priceService->getBars($request->all());
    }

    public function getSymbols(Request $request)
    {
        $searchKey = strtolower($request->input('symbol'));

        if (strpos($searchKey, '/') == false) {
            return;
        }

        $searchKey = explode('/', $searchKey);

        $currencyCoins = MasterdataService::getOneTable('coin_settings');

        $symbolData = $currencyCoins->first(function ($pair) use ($searchKey) {
            return $pair->coin == $searchKey[0] && $pair->currency === $searchKey[1];
        });

        if (! $symbolData) {
            return ["s" => "no_data"];
        }

        $pair = $symbolData->coin . "/" . $symbolData->currency;

        $price = $this->priceService->getPrice($symbolData->currency, $symbolData->coin);

        $symbol = [];
        $symbol['name']                  = $pair;
        $symbol['minmov']                = 1;
        $symbol['minmov2']               = 0;
        $symbol['pointvalue']            = 1;
        $symbol['session']               = "24x7";
        $symbol['has_intraday']          = true;
        $symbol['has_no_volume']         = false;
        $symbol['supported_resolutions'] = ["1","3","5","15","30","60","120","240", "360", "720", "D", "W","M"];
        $symbol['ticker']                = $pair;

        if (BigNumber::new($price->price)->comp(1) >= 0 || BigNumber::new($price->price)->comp(0) === 0) {
            $symbol['pricescale'] = 1000;
        } else {
            $scale = '1';
            for ($i = 0; $i < 10; $i++) {
                if (BigNumber::new(1)->div($scale)->comp($price->price) > 0) {
                    $scale = BigNumber::new($scale)->mul(10)->toString();
                } else {
                    $symbol['pricescale'] = BigNumber::new($scale)->mul(100)->toString();
                    break;
                }
            }
        }

        return $symbol;
    }

    public function getAllSymbols(Request $request)
    {
        $currencyCoins = MasterdataService::getOneTable('coin_settings');
        return $this->sendResponse($currencyCoins);
    }
}
