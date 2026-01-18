<?php

namespace App\Http\Controllers\API;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\MarketStatisticService;
use App\Models\MarketTag;
use Illuminate\Http\Request;

class MarketStatisticController extends AppBaseController
{
    private $marketStatisticService;

    public function __construct(MarketStatisticService $marketStatisticService)
    {
        $this->marketStatisticService = $marketStatisticService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/market-statistic",
     *     summary="[Public] Get Market Statistic",
     *     description="Get market statistic",
     *     tags={"Public API"},
     *     @OA\Response(
     *         response=200,
     *         description="Get market statistic info success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example="true"),
     *             @OA\Property(property="message", type="string", example="null"),
     *             @OA\Property(
     *                property="dataVersion",
     *                type="string",
     *                example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"
     *             ),
     *             @OA\Property(
     *                property="data",
     *                type="object",
     *                example={{"name": "eth","top": 1,"quantity": "1192.0000000000","lastest_price": "20.0000000000","changed_percent": "0","type": 0,"created_at": 1669709065000},
     *                         {"name": "btc","top": 2,"quantity": "159.2981618600","lastest_price": "34.0000000000","changed_percent": "0","type": 0,"created_at": 1669709065000},
     *                         {"name": "eos","top": 3,"quantity": "57.0000000000","lastest_price": null,"changed_percent": 0,"type": 0,"created_at": 1669709065000}
     *                         }
     *             ),
     *         )
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "success": false,
     *                 "message": "Server Error",
     *                 "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     *                 "data": null
     *             }
     *         )
     *     ),
     * )
     */
    public function getMarketStatistic()
    {
        $result = $this->marketStatisticService->getMarketStatistic();

        return $this->sendResponse($result);
    }

    public function getHotSymbols()
    {
        $result = null;
        $hotTag = MarketTag::where('type', Consts::MARKET_TAG_HOT)->first();
        if ($hotTag) {
            $result = $hotTag->symbols ? json_decode($hotTag->symbols) : null;
        }

        return $this->sendResponse($result);
    }
}
