<?php

namespace App\Http\Controllers\API;

use App\Consts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\PriceService;
use App\Http\Services\MasterdataService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\Price;

/**
 * Class PriceAPIController
 * @package App\Http\Controllers\API
 */
class PriceAPIController extends AppBaseController
{

    private $priceService;

    public function __construct()
    {
        $this->priceService = new PriceService();
    }

    /**
     * [Public] Get Prices
     * @group Spot exchange trading API
     *
     * @response {
     *  "success": true,
     *  "message": null,
     *  "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     *  "data": {
     * "usd_btc": {
     * "coin": "btc",
     * "currency": "usd",
     * "price": "1.0000000000",
     * "previous_price": "11935000.0000000000",
     * "change": "0",
     * "last_24h_price": "1.0000000000",
     * "volume": 0,
     * "created_at": 1568361587436
     * },
     * "usd_eth": {
     * "coin": "eth",
     * "currency": "usd",
     * "price": "962000.0000000000",
     * "previous_price": "962000.0000000000",
     * "change": "0",
     * "last_24h_price": "962000.0000000000",
     * "volume": 0,
     * "created_at": 1566270866883
     * },
     * "usd_amal": {
     * "coin": "amal",
     * "currency": "usd",
     * "price": "3.0000000000",
     * "previous_price": "10.0000000000",
     * "change": "0",
     * "last_24h_price": "3.0000000000",
     * "volume": 0,
     * "created_at": 1569818042358
     * }
     *  }
     * }
     *
     * @response 500 {
     *  "success": false,
     *  "message": "Server Error",
     *  "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     *  "data": null
     * }
     *
     * @response 401 {
     *  "message": "Unauthenticated."
     * }
     */

    public function getPrices(Request $request)
    {
        $coin = $request->coin;
        $data = $this->priceService->getPrices($coin);

        return $this->sendResponse($data);
    }

    /**
     * [Public] Get Price 24h
     * @group Spot exchange trading API
     *
     * @response {
     *  "success": true,
     *  "message": null,
     *  "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     *  "data": {
     * usd_btc": {
     * "current_price": "1.0000000000",
     * "changed_percent": "0",
     * "max_price": "1.0000000000",
     * "min_price": "1.0000000000",
     * "volume": 0,
     * "previous_price": "11935000.0000000000",
     * "currency": "usd",
     * "coin": "btc"
     * },
     * "usd_eth": {
     * "current_price": "962000.0000000000",
     * "changed_percent": "0",
     * "max_price": "962000.0000000000",
     * "min_price": "962000.0000000000",
     * "volume": 0,
     * "previous_price": "962000.0000000000",
     * "currency": "usd",
     * "coin": "eth"
     * },
     * "usd_amal": {
     * "current_price": "3.0000000000",
     * "changed_percent": "0",
     * "max_price": "3.0000000000",
     * "min_price": "3.0000000000",
     * "volume": 0,
     * "previous_price": "10.0000000000",
     * "currency": "usd",
     * "coin": "amal"
     * }
     *  }
     * }
     *
     * @response 500 {
     *  "success": false,
     *  "message": "Server Error",
     *  "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     *  "data": null
     * }
     *
     * @response 401 {
     *  "message": "Unauthenticated."
     * }
     */
    public function get24hPrices(Request $request): JsonResponse
    {
        $coin = $request->coin;
        $sort = $request->sort;
        $sort_type = $request->sort_type;
        $limit = $request->limit ?? 10;
        $data = $this->priceService->get24hPrices($coin);
        if ($coin) {
            $result = $data;

            if ($request->keyword) {
                $keyword = preg_quote($request->keyword, '~');
                $result = collect($result)->filter(function ($item) use ($keyword) {
                    return preg_grep('~'. $keyword.'~', array($item->coin));
                });
            }

            if ($sort && $sort_type) {
                $result = $this->priceService->sort24h(collect($result), $sort, $sort_type);
            }

            // $result = collect($result)->sortKeys();
            $data = paginate($result, $limit)->toArray();
        }
        return $this->sendResponse($data);
    }


    public function getPrice(Request $request)
    {
        $currency = $request->input('currency');
        $coin = $request->input('coin');

        $data = $this->priceService->getSinglePrice($currency, $coin);
        return $this->sendResponse($data);
    }

    public function getTicker()
    {
        $data = $this->priceService->getTicker();
        return $this->sendResponse($data);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/price-scope",
     *     summary="[Public] Price Scope",
     *     description="Price scope",
     *     tags={"Public API"},
     *     @OA\Parameter(
     *         description="The coin name",
     *         in="query",
     *         required=true,
     *         name="coin",
     *         @OA\Schema(
     *             type="string",
     *             example="eth"
     *         )
     *     ),
     *     @OA\Parameter(
     *         description="The currency name",
     *         in="query",
     *         required=true,
     *         name="currency",
     *         @OA\Schema(
     *             type="string",
     *             example="btc"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Get success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example="true"),
     *             @OA\Property(property="message", type="string", example=null),
     *             @OA\Property(
     *                property="dataVersion",
     *                type="string",
     *                example="21a153fff6c4068a3db8040b96b44865e3393d8e"
     *             ),
     *             @OA\Property(
     *                property="data",
     *                type="object",
     *                example={
     *                    "current_price": "6.0000000000",
     *                    "changed_percent": "0",
     *                    "max_price": "6.0000000000",
     *                    "min_price": "6.0000000000",
     *                    "volume": 0,
     *                    "quote_volume": null,
     *                    "previous_price": "6.0000000000",
     *                    "currency": "btc",
     *                    "coin": "eth"
     *                }
     *             ),
     *         )
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @OA\JsonContent(type="object", example={"message": "Unauthenticated."})
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
     *     )
     * )
     */
    public function getPriceScopeIn24h(Request $request)
    {
        $currency = $request->input('currency');
        $coin = $request->input('coin');
        $data = $this->priceService->getPriceScopeIn24h($currency, $coin);
        return $this->sendResponse($data);
    }

    public function getMarketInfo()
    {
        $data = $this->priceService->getMarketInfo();

        return $this->sendResponse($data);
    }

    public function getExternalPrice($market, $currency, $coin)
    {
        return DB::table("{$coin}_{$currency}_{$market}_orders")
            ->orderBy('id', 'desc')
            ->take(1)
            ->pluck('price');
    }

    public function getPricesHistory()
    {
        $data = $this->priceService->getPricesHitory();
        return $this->sendResponse($data);
    }

    public function getTrendingSymbols()
    {
        //TODO Thanh Tran
        $allSymbols = MasterdataService::getOneTable('coin_settings');
        $trendingSymbols = array_slice($allSymbols->toArray(), 0, 5);
        return $this->sendResponse($trendingSymbols);
    }
}
