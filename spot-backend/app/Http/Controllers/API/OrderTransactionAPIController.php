<?php
/**
 * Created by PhpStorm.
 * Date: 9/6/19
 * Time: 2:58 PM
 */

namespace App\Http\Controllers\API;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Http\Resources\MyOrderTransactionResource;
use App\Models\OrderTransaction;
use Illuminate\Http\Request;
use App\Utils;
use Carbon\Carbon;

class OrderTransactionAPIController extends AppBaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/order-transaction-latest",
     *     summary="[Public] Order Transaction Latest",
     *     description="Get latest order transaction",
     *     tags={"Public API"},
     *     @OA\Parameter(
     *         description="The currency name.",
     *         in="query",
     *         name="currency",
     *         @OA\Schema(type="string", example="btc")
     *     ),
     *     @OA\Parameter(
     *         description="The coin name.",
     *         in="query",
     *         name="coin",
     *         @OA\Schema(type="string", example="amal")
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
     *                example="c4c5e35f610222a9cd29d500356e4b790cf21642"
     *             ),
     *             @OA\Property(
     *                property="data",
     *                type="object",
     *                example=null
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @OA\JsonContent(type="object", example={"message": "Unauthenticated."})
     *     ),
     *     @OA\Response(
     *         response="419",
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
    public function getLatest()
    {
        request()->validate([
            'currency' => 'required',
            'coin' => 'required',
        ]);

        $input = request()->only('currency', 'coin');

        try {
            $orderTransaction = OrderTransaction::where($input)->latest()->first();

            $res = null;

            if ($orderTransaction) {
                $res = [
                    'orderTransaction' => $this->getOrderTransaction($orderTransaction),
                    'buyOrder' => $this->getBuyOrder($orderTransaction),
                    'sellOrder' => $this->getSellOrder($orderTransaction),
                ];
            }

            return $this->sendResponse($res);
        } catch (\Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    private function getOrderTransaction($orderTransaction)
    {
        return [
            "id" => $orderTransaction->id,
            "transaction_type" => $orderTransaction->transaction_type,
            "buy_order_id" => $orderTransaction->buy_order_id,
            "sell_order_id" => $orderTransaction->sell_order_id,
            "quantity" => $orderTransaction->quantity,
            "price" => $orderTransaction->price,
            "currency" => $orderTransaction->currency,
            "coin" => $orderTransaction->coin,
            "amount" => $orderTransaction->amount,
            "status" => $orderTransaction->status,
            "created_at" => $this->convertTime($orderTransaction->created_at),
            "executed_date" => $orderTransaction->executed_date,
            "sell_fee" => $orderTransaction->sell_fee,
            "buy_fee" => $orderTransaction->buy_fee,
            "buyer_id" => $orderTransaction->buyer_id,
            "seller_id" => $orderTransaction->seller_id,
        ];
    }

    private function getBuyOrder($orderTransaction)
    {
        return [
            "id" => $orderTransaction->buy_order_id,
            "user_id" => $orderTransaction->buyer_id,
            "trade_type" => $orderTransaction->transaction_type,
            "currency" => $orderTransaction->currency,
            "coin" => $orderTransaction->coin,
            "quantity" => $orderTransaction->quantity,
            "price" => $orderTransaction->price,
            "fee" => $orderTransaction->sell_fee,
            "status" => $orderTransaction->status,
            "created_at" => $this->convertTime($orderTransaction->created_at),
        ];
    }

    private function getSellOrder($orderTransaction)
    {
        return [
            "id" => $orderTransaction->sell_order_id,
            "user_id" => $orderTransaction->seller_id,
            "trade_type" => $orderTransaction->transaction_type,
            "currency" => $orderTransaction->currency,
            "coin" => $orderTransaction->coin,
            "quantity" => $orderTransaction->quantity,
            "price" => $orderTransaction->price,
            "fee" => $orderTransaction->buy_fee,
            "status" => $orderTransaction->status,
            "created_at" => $this->convertTime($orderTransaction->created_at),
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/v1/my-order-transactions",
     *     summary="[Private] My Trade History",
     *     description="Get trade histories",
     *     tags={"Private API"},
     *     @OA\Parameter(
     *         description="The coin name - currency name",
     *         in="query",
     *         required=true,
     *         name="symbol",
     *         @OA\Schema(
     *             type="string",
     *             example="xrp-btc"
     *         )
     *     ),
     *     @OA\Parameter(
     *         description="The start date",
     *         in="query",
     *         required=true,
     *         name="startDate",
     *         @OA\Schema(
     *             type="string",
     *             example="2022-01-02 00:00:00"
     *         )
     *     ),
     *     @OA\Parameter(
     *         description="The end date",
     *         in="query",
     *         name="endDate",
     *         @OA\Schema(
     *             type="string",
     *             example="2022-09-02 00:00:00"
     *         )
     *     ),
     *     @OA\Parameter(description="Current page", in="query", name="page",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Parameter(description="Number items of per page", in="query", name="limit",
     *         @OA\Schema(
     *             type="integer",
     *             example=10
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Get histories success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example="true"),
     *             @OA\Property(property="message", type="string", example=null),
     *             @OA\Property(
     *                property="dataVersion",
     *                type="string",
     *                example="96edae4140d6b195f3d75ccb0a49bc340842982e"
     *             ),
     *             @OA\Property(
     *                property="data",
     *                type="object",
     *                @OA\Property(property="status", type="boolean", example=true),
     *                @OA\Property(property="total", type="integer", example=1),
     *                @OA\Property(property="last_page", type="integer", example=1),
     *                @OA\Property(property="per_page", type="integer", example=15),
     *                @OA\Property(property="current_page", type="integer", example=1),
     *                @OA\Property(property="next_page_url", type="string", example=null),
     *                @OA\Property(property="prev_page_url", type="string", example=null),
     *                @OA\Property(property="from", type="integer", example=1),
     *                @OA\Property(property="to", type="integer", example=1),
     *                @OA\Property(
     *                    property="data",
     *                    type="array",
     *                    example={
     *                        {
     *                            "id": 19,
     *                             "volume": "2.0000000000",
     *                            "side": "sell",
     *                            "feeCoin": "0.0320000000",
     *                            "price": "8.0000000000",
     *                            "fee": "btc",
     *                            "ctime": 1566535004889,
     *                            "deal_price": "8.0000000000",
     *                            "type": "Purchase",
     *                            "bid_id": 44,
     *                            "ask_id": 46,
     *                            "bid_user_id": 1053,
     *                            "ask_user_id": 1027
     *                        },
     *                        {
     *                            "id": 20,
     *                            "volume": "2.0000000000",
     *                            "side": "sell",
     *                            "feeCoin": "0.0320000000",
     *                            "price": "8.0000000000",
     *                            "fee": "btc",
     *                            "ctime": 1566535004889,
     *                            "deal_price": "8.0000000000",
     *                            "type": "Purchase",
     *                            "bid_id": 44,
     *                            "ask_id": 46,
     *                            "bid_user_id": 1053,
     *                            "ask_user_id": 1027
     *                        }
     *                    },
     *                    @OA\Items(
     *                        @OA\Property(property="trade_type", type="string", example="sell"),
     *                    ),
     *                )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @OA\JsonContent(type="object", example={"message": "Unauthenticated."})
     *     ),
     *     @OA\Response(
     *         response="419",
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
     *     ),
     *     security={{ "apiAuth": {} }}
     * )
     */
    public function getAllTradeHistory()
    {
        \request()->validate([
            'symbol' => 'required',
            'startDate' => 'required',
        ]);

        try {
            $symbols = explode('-', \request('symbol'));

            $currency = $symbols[1];
            $coin = $symbols[0];
            $userId = auth('api')->id();

            $startDate = strtotime(request('startDate')) * 1000;
            if ($startDate == 0) {
                $startDate = Utils::previous24hInMillis();
            }

            $filter = compact('currency', 'coin');

            $data = OrderTransaction::where($filter)
                ->where(function ($query) use ($userId) {
                    $query->where('buyer_id', $userId)
                        ->orWhere('seller_id', $userId);
                })
                ->where('created_at', '>=', $startDate)
                ->when(request('endDate'), function ($query) {
                    $query->where('created_at', '<=', strtotime(request('endDate')) * 1000);
                })
                ->paginate(\request('limit', Consts::DEFAULT_PER_PAGE));

            $resources = new MyOrderTransactionResource($data);

            return $this->sendResponse($resources);
        } catch (\Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/get-trades-by-order-id/{id}",
     *     summary="[Private] Get Trades By Order Id",
     *     description="Get trades by order id",
     *     tags={"Private API"},
     *     @OA\Parameter(
     *         description="Id of order",
     *         in="path",
     *         required=true,
     *         name="id",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
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
     *                example="96edae4140d6b195f3d75ccb0a49bc340842982e"
     *             ),
     *             @OA\Property(
     *                property="data",
     *                type="object",
     *                @OA\Property(property="current_page", type="integer", example=1),
     *                @OA\Property(
     *                    property="data",
     *                    type="object",
     *                    @OA\Property(property="status", type="boolean", example=true),
     *                    @OA\Property(property="total", type="integer", example=1),
     *                    @OA\Property(property="last_page", type="integer", example=1),
     *                    @OA\Property(property="per_page", type="integer", example=15),
     *                    @OA\Property(property="current_page", type="integer", example=1),
     *                    @OA\Property(property="next_page_url", type="string", example=null),
     *                    @OA\Property(property="prev_page_url", type="string", example=null),
     *                    @OA\Property(property="from", type="integer", example=1),
     *                    @OA\Property(property="to", type="integer", example=1),
     *                    @OA\Property(
     *                        property="data",
     *                        type="array",
     *                        example={
     *                            {
     *                                "id": 19,
     *                                "volume": "2.0000000000",
     *                                "side": "sell",
     *                                "feeCoin": "0.0320000000",
     *                                "price": "8.0000000000",
     *                                "fee": "btc",
     *                                "ctime": 1566535004889,
     *                                "deal_price": "8.0000000000",
     *                                "type": "Purchase",
     *                                "bid_id": 44,
     *                                "ask_id": 46,
     *                                "bid_user_id": 1053,
     *                                "ask_user_id": 1027
     *                            },
     *                            {
     *                                "id": 20,
     *                                "volume": "2.0000000000",
     *                                "side": "sell",
     *                                "feeCoin": "0.0320000000",
     *                                "price": "8.0000000000",
     *                                "fee": "btc",
     *                                "ctime": 1566535004889,
     *                                "deal_price": "8.0000000000",
     *                                "type": "Purchase",
     *                                "bid_id": 44,
     *                                "ask_id": 46,
     *                                "bid_user_id": 1053,
     *                                "ask_user_id": 1027
     *                            }
     *                        },
     *                        @OA\Items(
     *                            @OA\Property(property="trade_type", type="string", example="sell"),
     *                        )
     *                    )
     *                )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @OA\JsonContent(type="object", example={"message": "Unauthenticated."})
     *     ),
     *     @OA\Response(
     *         response="419",
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
     *     ),
     *     security={{ "apiAuth": {} }}
     * )
     */
    public function getByOrderId($id)
    {
        $userId = auth('api')->id();

        try {
            $data = OrderTransaction::where(function ($query) use ($id) {
                $query->where('buy_order_id', $id)
                    ->orWhere('sell_order_id', $id);
            })->where(function ($query) use ($userId) {
                $query->where('buyer_id', $userId)
                    ->orWhere('seller_id', $userId);
            })->paginate();

            $resources = new MyOrderTransactionResource($data);

            return $this->sendResponse($resources);
        } catch (\Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    private function convertTime($time)
    {
        $timezoneOffset = Carbon::now()->offset;
        $timeReturn = Utils::millisecondsToDateTime($time, $timezoneOffset, 'Y-m-d');
        return $timeReturn;
    }
}
