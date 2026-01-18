<?php

namespace App\Http\Controllers\API;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Http\Requests\CreateOrderAPIRequest;
use App\Http\Services\CircuitBreakerService;
use App\Http\Services\EnableTradingSettingService;
use App\Http\Services\OrderService;
use App\Http\Services\PriceService;
use App\Http\Services\UserService;
use App\Models\Order;
use App\Models\TrandingLimit;
use App\Utils\BigNumber;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\UrlParam;

/**
 * @subgroup Order
 *
 * @authenticated
 */
class OrderAPIController extends AppBaseController
{
    private OrderService $orderService;
    private UserService $userService;
    private CircuitBreakerService $circuitBreakerService;

    public function __construct()
    {
        $this->orderService = new OrderService();
        $this->circuitBreakerService = new CircuitBreakerService();
        $this->userService = new UserService();
    }

    /**
     * Create order
     *
     * @param CreateOrderAPIRequest $request
     *
     * @return JsonResponse
     *
     * @throws \Throwable
     *
     * @response {
     * "id": 1,
     * "original_id": null,
     * "user_id": 1,
     * "email": "bot1@gmail.com",
     * "trade_type": "buy",
     * "currency": "btc",
     * "coin": "eth",
     * "type": "limit",
     * "ioc": null,
     * "quantity": "1222.0000000000",
     * "price": "0.0468640000",
     * "executed_quantity": "46.9910000000",
     * "executed_price": "0.0468640000",
     * "base_price": null,
     * "stop_condition": null,
     * "fee": "0.0704865000",
     * "status": "pending",
     * "created_at": 1566440661180,
     * "updated_at": 1566440661180
     * }
     *
     * @response 500 {
     * "success": false,
     * "message": "Server Error",
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": null
     * }
     *
     * @response 401 {
     * "message": "Unauthenticated."
     * }
     */
    #[BodyParam("coin", "string", "Coin order. ", required: true, example: 'ltc')]
    #[BodyParam("balance", "string", "Balance of user. ", required: true, example: '100000')]
    #[BodyParam("currency", "string", "Currency order. ", required: true, example: 'usdt')]
    #[BodyParam("lang", "string", "Language. ", required: false, example: 'en')]
    #[BodyParam("quantity", "string", "Quantity order. ", required: true, example: 5)]
    #[BodyParam("trade_type", "string", "Trade type (buy or sell). ", required: true, example: 'buy')]
    #[BodyParam("type", "string", "Type of order (limit or market). ", required: true, example: 'market')]
    #[BodyParam("total", "string", "Total if type is limit. ", required: false, example: '100')]
    #[BodyParam("price", "string", "Price if type is limit. ", required: false, example: '100')]

    /**
     * @OA\Post (
     *     path="/orders",
     *     tags={"Trading"},
     *     summary="[Private] Place new order (TRADE)",
     *     description="Create order",
     *     @OA\RequestBody(
     *         required=true,
     *          @OA\JsonContent(
     *              required={"coin","balance","currency","quantity","trade_type","type"},
     *              @OA\Property(property="coin", description="Coin order.", type="string", format="string", example="sol"),
     *              @OA\Property(property="currency", description="Balance of user.", type="string", format="string", example="usd"),
     *              @OA\Property(property="price", description="Price if type is limit.", type="string", format="string", example="10"),
     *              @OA\Property(property="base_price", description="", type="string", format="string", example="1"),
     *              @OA\Property(property="stop_condition", description="Language.", type="string", format="string", example="ge"),
     *              @OA\Property(property="quantity", description="Quantity order.", type="string", format="string", example="1"),
     *              @OA\Property(property="trade_type", description="Trade type (buy or sell).", type="string", format="string", example="sell"),
     *              @OA\Property(property="type", description="Type of order (limit or market).", type="string", format="string", example="limit"),
     *              @OA\Property(property="total", description="Total if type is limit.", type="string", format="string", example="100")
     *        )
     *      ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="null"),
     *              @OA\Property(property="dataVersion", type="string", example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="original_id", type="integer", nullable=true),
     *                  @OA\Property(property="user_id", type="integer", example=1),
     *                  @OA\Property(property="email", type="string", format="email", example="bot1@gmail.com"),
     *                  @OA\Property(property="trade_type", type="string", example="sell"),
     *                  @OA\Property(property="currency", type="string", example="usd"),
     *                  @OA\Property(property="coin", type="string", example="sol"),
     *                  @OA\Property(property="type", type="string", example="limit"),
     *                  @OA\Property(property="ioc", type="string", nullable=true),
     *                  @OA\Property(property="quantity", type="string", example="1.0000000000"),
     *                  @OA\Property(property="price", type="string", example="10.0000000000"),
     *                  @OA\Property(property="executed_quantity", type="string", example="0.0000000000"),
     *                  @OA\Property(property="executed_price", type="string", example="0.0000000000"),
     *                  @OA\Property(property="base_price", type="string", example="1.0000000000"),
     *                  @OA\Property(property="stop_condition", type="string", example="ge"),
     *                  @OA\Property(property="fee", type="string", example="0.0000000000"),
     *                  @OA\Property(property="status", type="string", example="new"),
     *                  @OA\Property(property="created_at", type="integer", example=1717750039293),
     *                  @OA\Property(property="updated_at", type="integer", example=1717750039293),
     *                  @OA\Property(property="market_type", type="integer", example=0),
     *                  example={"id": 1,"original_id": null,"user_id": 1,"email": "bot1@gmail.com","trade_type": "sell","currency": "usd","coin": "sol","type": "limit","ioc": null,"quantity": "1.0000000000","price": "10.0000000000","executed_quantity": "0.0000000000","executed_price": "0.0000000000","base_price": "1.0000000000","stop_condition": "ge","fee": "0.0000000000","status": "new","created_at": 1717750039293,"updated_at": 1717750039293,"market_type": 0}
     *					),
     *          )
     *      ),
     *      @OA\Response(
     *        response=500,
     *        description="Server error",
     *        @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Server Error"),
     *              @OA\Property(property="dataVersion", type="string", example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"),
     *              @OA\Property(property="data", type="string", example=null)
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated.")
     *          )
     *      ),
     *      security={{ "apiAuth": {} }}
     * )
     */
    public function store(CreateOrderAPIRequest $request)
    {
        // Check allow trading with Circuit Breaker
        $coin = $request->coin;
        $currency = $request->currency;
        $allowTrading = $this->circuitBreakerService->checkAllowTradingCoinPair($currency, $coin);

        if ($allowTrading == Consts::CIRCUIT_BREAKER_BLOCK_TRADING_STATUS) {
            return $this->sendError(__('circuit_breaker_setting.validation.block_trading'));
        }


        $enableTradingService = new EnableTradingSettingService();
        $allowTrading = $enableTradingService->checkAllowTrading($currency, $coin, $request->user()->email);
        if (!$allowTrading) {
            return $this->sendError(__('enable_trading.validation.block_trading'));
        }

        $result = $this->blockOrderIfNeeded($request);
        if ($result) {
            return $result;
        }

        $balanceCurrency = $this->userService->getDetailsUserBalance(Auth::id(), $currency, false,
            Consts::TYPE_EXCHANGE_BALANCE);
        $balanceCoin = $this->userService->getDetailsUserBalance(Auth::id(), $coin, false,
            Consts::TYPE_EXCHANGE_BALANCE);
        if ($balanceCurrency && $request->trade_type == Consts::ORDER_TRADE_TYPE_BUY && BigNumber::new($balanceCurrency->available_balance)->sub($request->quantity * $request->price)->toString() < 0) {
            return $this->sendError(__('enable_trading.validation.insufficient_balance'));
        }
        if ($balanceCoin && $request->trade_type == Consts::ORDER_TRADE_TYPE_SELL && BigNumber::new($balanceCoin->available_balance)->sub($request->quantity)->toString() < 0) {
            return $this->sendError(__('enable_trading.validation.insufficient_balance'));
        }

        try {
            $order = $this->orderService->createOrder($request);
            return $this->sendResponse($order);
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    private function blockOrderIfNeeded($request)
    {
        $user = $request->user();
        if ($user->type === 'bot') {
            return;
        }

        $coin = $request->coin;
        $currency = $request->currency;

        $isCircuitBreakerEnabled = $this->circuitBreakerService->isCircuitBreakerEnabled($currency, $coin);
        if ($isCircuitBreakerEnabled) {
            if ($this->isMarketOrderRequest($request)) {
                $type = $request->input('type', '');
                if ($type === Consts::ORDER_TYPE_MARKET) {
                    return $this->sendError(__('circuit_breaker_setting.validation.block_market_order'));
                } else {
                    return $this->sendError(__('circuit_breaker_setting.validation.block_stop_market_order'));
                }
            } else {
                $pairSetting = $this->circuitBreakerService->getCoinPairSetting($currency, $coin);
                $priceService = new PriceService();
                $price = $priceService->getPrice($currency, $coin)->price;
                $orderPrice = $request->input('price', '');
                if ($pairSetting && $price && $orderPrice) {
                    $minPrice = BigNumber::new(100)->sub($pairSetting->circuit_breaker_percent)->mul($price)->div(100);
                    $maxPrice = BigNumber::new(100)->add($pairSetting->circuit_breaker_percent)->mul($price)->div(100);
                    if ($minPrice->comp($orderPrice) >= 0) {
                        return $this->sendError(__('circuit_breaker_setting.validation.price_too_low'));
                    }
                    if ($maxPrice->comp($orderPrice) <= 0) {
                        return $this->sendError(__('circuit_breaker_setting.validation.price_too_high'));
                    }
                }
            }
        }
    }

    private function isMarketOrderRequest($request): bool
    {
        $type = $request->input('type', '');
        return $type === Consts::ORDER_TYPE_MARKET || $type === Consts::ORDER_TYPE_STOP_MARKET;
    }

    /**
     * Cancel order by id order of user
     * @param Request $request
     * @param $id
     * @return JsonResponse
     *
     * @response 200 {
     * "success": true,
     * "message": null,
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": null
     * }
     *
     * @response 500 {
     * "success": false,
     * "message": "Server Error",
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": null
     * }
     *
     * @response 401 {
     * "message": "Unauthenticated.",
     * }
     */
    #[UrlParam("id", "int", "ID of order. ", required: true, example: 1)]

    /**
     * @OA\Put (
     *     path="/orders/:id/cancel",
     *     tags={"Trading"},
     *     summary="[Private] Cancel order (TRADE)",
     *     description="Cancel order by id order of user",
     *     @OA\Parameter(
     *           name="id",
     *           in="path",
     *           description="ID of order.",
     *           @OA\Schema(
     *               type="int",
     *               example=1
     *           )
     *       ),
     *     @OA\Response(
     *          response=200,
     *          description="Successful response",
     *           @OA\JsonContent(
     *               @OA\Property(property="success", type="boolean", example=true),
     *               @OA\Property(property="message", type="string", example="null"),
     *               @OA\Property(property="dataVersion", type="string", example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"),
     *               @OA\Property(property="data", type="string", example=""),
     *         )
     *      ),
     *     @OA\Response(
     *        response=500,
     *        description="Server error",
     *        @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Server Error"),
     *              @OA\Property(property="dataVersion", type="string", example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"),
     *              @OA\Property(property="data", type="string", example=null)
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated.")
     *          )
     *      ),
     *      security={{ "apiAuth": {} }}
     * )
     *
     */

    public function cancel(Request $request, $id)
    {
        try {
            $user = $request->user();
            $this->orderService->cancel($user->id, $id);

            return $this->sendResponse('');
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * @OA\Put (
     *     path="/orders/:id/replace",
     *     tags={"Trading"},
     *     summary="[Private] Cancel and replace order (TRADE)",
     *     description="Cancel an existing order and immediately place a new order instead of the canceled one by id order of user.",
     *     @OA\Parameter(
     *           name="id",
     *           in="path",
     *           description="ID of order cancel.",
     *           @OA\Schema(
     *               type="int",
     *               example=1
     *           )
     *       ),
     *     @OA\RequestBody(
     *          required=true,
     *           @OA\JsonContent(
     *               required={"coin","balance","currency","quantity","trade_type","type"},
     *               @OA\Property(property="coin", description="Coin order.", type="string", format="string", example="sol"),
     *               @OA\Property(property="currency", description="Balance of user.", type="string", format="string", example="usd"),
     *               @OA\Property(property="price", description="Price if type is limit.", type="string", format="string", example="10"),
     *               @OA\Property(property="base_price", description="", type="string", format="string", example="1"),
     *               @OA\Property(property="stop_condition", description="Language.", type="string", format="string", example="ge"),
     *               @OA\Property(property="quantity", description="Quantity order.", type="string", format="string", example="1"),
     *               @OA\Property(property="trade_type", description="Trade type (buy or sell).", type="string", format="string", example="sell"),
     *               @OA\Property(property="type", description="Type of order (limit or market).", type="string", format="string", example="limit"),
     *               @OA\Property(property="total", description="Total if type is limit.", type="string", format="string", example="100")
     *         )
     *       ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful response",
     *           @OA\JsonContent(
     *               @OA\Property(property="success", type="boolean", example=true),
     *               @OA\Property(property="message", type="string", example="null"),
     *               @OA\Property(property="dataVersion", type="string", example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"),
     *               @OA\Property(property="data", type="object",
     *                   @OA\Property(property="cancelOrigClientOrderId", type="string", example="22"),
     *                   @OA\Property(property="newOrderResponse", type="object",
     *                        @OA\Property(property="id", type="integer", example=1),
     *                        @OA\Property(property="original_id", type="integer", nullable=true),
     *                        @OA\Property(property="user_id", type="integer", example=1),
     *                        @OA\Property(property="email", type="string", format="email", example="bot1@gmail.com"),
     *                        @OA\Property(property="trade_type", type="string", example="sell"),
     *                        @OA\Property(property="currency", type="string", example="usd"),
     *                        @OA\Property(property="coin", type="string", example="sol"),
     *                        @OA\Property(property="type", type="string", example="limit"),
     *                        @OA\Property(property="ioc", type="string", nullable=true),
     *                        @OA\Property(property="quantity", type="string", example="1.0000000000"),
     *                        @OA\Property(property="price", type="string", example="10.0000000000"),
     *                        @OA\Property(property="executed_quantity", type="string", example="0.0000000000"),
     *                        @OA\Property(property="executed_price", type="string", example="0.0000000000"),
     *                        @OA\Property(property="base_price", type="string", example="1.0000000000"),
     *                        @OA\Property(property="stop_condition", type="string", example="ge"),
     *                        @OA\Property(property="fee", type="string", example="0.0000000000"),
     *                        @OA\Property(property="status", type="string", example="new"),
     *                        @OA\Property(property="created_at", type="integer", example=1717750039293),
     *                        @OA\Property(property="updated_at", type="integer", example=1717750039293),
     *                        @OA\Property(property="market_type", type="integer", example=0),
     *                        example={"id": 1,"original_id": null,"user_id": 1,"email": "bot1@gmail.com","trade_type": "sell","currency": "usd","coin": "sol","type": "limit","ioc": null,"quantity": "1.0000000000","price": "10.0000000000","executed_quantity": "0.0000000000","executed_price": "0.0000000000","base_price": "1.0000000000","stop_condition": "ge","fee": "0.0000000000","status": "new","created_at": 1717750039293,"updated_at": 1717750039293,"market_type": 0}
     *                    ),
     *                    example={"cancelOrigClientOrderId":"22", "newOrderResponse":{"id": 1,"original_id": null,"user_id": 1,"email": "bot1@gmail.com","trade_type": "sell","currency": "usd","coin": "sol","type": "limit","ioc": null,"quantity": "1.0000000000","price": "10.0000000000","executed_quantity": "0.0000000000","executed_price": "0.0000000000","base_price": "1.0000000000","stop_condition": "ge","fee": "0.0000000000","status": "new","created_at": 1717750039293,"updated_at": 1717750039293,"market_type": 0}}
     *                ),
     *             ),
     *      ),
     *     @OA\Response(
     *        response=500,
     *        description="Server error",
     *        @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Server Error"),
     *              @OA\Property(property="dataVersion", type="string", example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"),
     *              @OA\Property(property="data", type="string", example=null)
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated.")
     *          )
     *      ),
     *      security={{ "apiAuth": {} }}
     * )
     *
     */

    public function replace(CreateOrderAPIRequest $request, $id)
    {
        try {
            $user = $request->user();
            logger()->info('=== Replace order: cancel order exist');
            $this->orderService->cancel($user->id, $id);
            logger()->info('=== Replace order: Create new order');
            $newOrderResponse = $this->store($request);
            logger()->info('=== #Replace order done');

            return $this->sendResponse(['cancelOrigClientOrderId' => $id, "newOrderResponse" => collect($newOrderResponse->getData())->get('data')]);
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }


    /**
     * Cancel all order of user
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @response 200 {
     * "success": true,
     * "message": null,
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": null
     * }
     *
     * @response 500 {
     * "success": false,
     * "message": "Server Error",
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": null
     * }
     *
     * @response 401 {
     * "message": "Unauthenticated.",
     * }
     */

    /**
     * @OA\Put (
     *     path="/orders/cancel-all",
     *     tags={"Trading"},
     *     summary="[Private] Cancel open orders (TRADE)",
     *     description="Cancel all order of user",
     *     @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *     required = {"coin","currency"},
     *           @OA\Property (property="coin", type="string", example="sol"),
     *           @OA\Property (property="currency", type="string", example="usd"),
     *           @OA\Property (property="market_type", type="int", example=0)
     *       )
     *     ),
     *     @OA\Response(
     *          response=200,
     *          description="Successful response",
     *           @OA\JsonContent(
     *               @OA\Property(property="success", type="boolean", example=true),
     *               @OA\Property(property="message", type="string", example="null"),
     *               @OA\Property(property="dataVersion", type="string", example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"),
     *         )
     *      ),
     *     @OA\Response(
     *        response=500,
     *        description="Server error",
     *        @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Server Error"),
     *              @OA\Property(property="dataVersion", type="string", example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"),
     *              @OA\Property(property="data", type="string", example=null)
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated.")
     *          )
     *      ),
     *      security={{ "apiAuth": {} }}
     * )
     *
     */
    public function cancelAll(Request $request): JsonResponse
    {
        $filter = $request->only('currency', 'coin');

        try {
            $user_id = Auth::id();
            if (isset($request->market_type)) {
                $this->orderService->cancelAll($user_id, $filter, $request->market_type);
            } else {
                $this->orderService->cancelAll($user_id, $filter);
            }

            return $this->sendResponse(true);
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    /**
     * Cancel order by type of user
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @response 200 {
     * "success": true,
     * "message": null,
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": null
     * }
     *
     * @response 500 {
     * "success": false,
     * "message": "Server Error",
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": null
     * }
     *
     * @response 401 {
     * "message": "Unauthenticated.",
     * }
     */
    #[BodyParam("type", "string", "Type of order (limit or market). ", required: true, example: 'limit')]
    public function cancelByType(Request $request): JsonResponse
    {
        try {
            $user_id = Auth::id();
            $this->orderService->cancelByType($user_id, $request->input('type'));
            return $this->sendResponse(true);
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage(), 422);
        }
    }

    /* Backup
     * @summary="[Private] Get User Order History",
     * @description="Get user order histories",
     * @tags={"Private API"},
     *
    */

    /**
     * @OA\Get(
     *     path="/api/v1/orders/transactions",
     *     summary="[Private] Get User transactions History",
     *     description="Get User transactions History",
     *     tags={"Private API"},
     *     @OA\Parameter(
     *         description="Current page",
     *         in="query",
     *         name="page",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Parameter(
     *         description="Number items of per page",
     *         in="query",
     *         name="limit",
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
     *             @OA\Property(property="message", type="string", example="Jessica Jones"),
     *             @OA\Property(
     *                property="dataVersion",
     *                type="string",
     *                example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"
     *             ),
     *             @OA\Property(
     *                property="data",
     *                type="array",
     *                example={
     *                    {"created_at": 1567043630932, "price": "0.0468640000", "quantity": "3.9940000000", "transaction_type": "sell"},
     *                    {"created_at": 1567043630932, "price": "0.0468640000", "quantity": "3.9940000000", "transaction_type": "sell"}
     *                },
     *                @OA\Items(
     *                    @OA\Property(type="object", example={}),
     *                ),
     *             ),
     *         )
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "message": "Unauthenticated.",
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response="419",
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             example={
     *                 "message": "Unauthenticated.",
     *             }
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
     *     security={{ "apiAuth": {} }}
     * )
     */

    /**
     * Transaction History
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @response 200 {
     * "success": true,
     * "message": null,
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": [{"created_at": 1567043630932, "price": "0.0468640000", "quantity": "3.9940000000", "transaction_type": "sell"},
     *                    {"created_at": 1567043630932, "price": "0.0468640000", "quantity": "3.9940000000", "transaction_type": "sell"}]
     * }
     *
     * @response 500 {
     * "success": false,
     * "message": "Server Error",
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": null
     * }
     *
     * @response 401 {
     * "message": "Unauthenticated.",
     * }
     */
    #[QueryParam("page", "int", "Page. ", required: false, example: 1)]
    #[QueryParam("limit", "int", "Limit. ", required: false, example: 10)]
    public function getTransactionHistory(Request $request): JsonResponse
    {
        try {
            $transaction = $this->orderService->getTransactionsWithPagination($request->all(), Auth::id());

            return $this->sendResponse($transaction);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function getTransactionHistoryForUser(Request $request): JsonResponse
    {
        $params = $request->all();
        $userId = $request->input('user_id', -1);
        $transactions = $this->orderService->getTransactionsWithPagination($params, $userId);

        return $this->sendResponse($transactions);
    }

    public function getUserTransactions(Request $request): JsonResponse
    {
        $currency = $request->input('currency');
        $coin = $request->input('coin');
        $count = $request->input('count', Consts::DEFAULT_PER_PAGE);
        $userId = Auth::id();
        $transactions = $this->orderService->getRecentTransactions($currency, $coin, $count, $userId);
        return $this->sendResponse($transactions);
    }

    /**
     * Get orderbook for coinmarketcap api
     *
     * @param Request $request
     * @param $pair
     * @return JsonResponse
     */
    public function getCmcOrderbook(Request $request, String $pair): JsonResponse
    {
        $pair = explode('_', $pair);
        $coin = isset($pair[0]) ? strtolower($pair[0]) : '';
        $currency = isset($pair[1]) ? strtolower($pair[1]) : '';


        $orderbooks = $this->orderService->getCmcOrderbook($currency, $coin, $request->all());
        return $this->sendResponse($orderbooks);
    }

    /**
     * Order pending of user
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @response {
     * "data": {
     * "current_page": 1,
     * "data": [
     * {
     * "id": 708,
     * "original_id": null,
     * "user_id": 2,
     * "email": "bot2@gmail.com",
     * "trade_type": "sell",
     * "currency": "usd",
     * "coin": "ltc",
     * "type": "limit",
     * "ioc": null,
     * "quantity": "100.0000000000",
     * "price": "2.0000000000",
     * "executed_quantity": "0.0000000000",
     * "executed_price": "0.0000000000",
     * "base_price": null,
     * "stop_condition": null,
     * "fee": "0.0000000000",
     * "status": "pending",
     * "created_at": 1567150919521,
     * "updated_at": 1567150919521,
     * "total": "200.00000000000000000000"
     * },
     * {
     * "id": 709,
     * "original_id": null,
     * "user_id": 2,
     * "email": "bot2@gmail.com",
     * "trade_type": "sell",
     * "currency": "usd",
     * "coin": "ltc",
     * "type": "limit",
     * "ioc": null,
     * "quantity": "100.0000000000",
     * "price": "2.0000000000",
     * "executed_quantity": "0.0000000000",
     * "executed_price": "0.0000000000",
     * "base_price": null, "stop_condition": null,
     * "fee": "0.0000000000",
     * "status": "pending",
     * "created_at": 1567150919521,
     * "updated_at": 1567150919521,
     * "total": "200.00000000000000000000"
     * }
     * ],
     * "first_page_url": "http://localhost:8080/api/v1/orders/pending?page=1",
     * "from": 1,
     * "last_page": 1,
     * "last_page_url": "http://localhost:8080/api/v1/orders/pending?page=1",
     * "links": [
     * {
     * "url": null,
     * "label": "&laquo; Previous",
     * "active": false
     * },
     * {
     * "url": "http://localhost:8080/api/v1/orders/pending?page=1",
     * "label": "1",
     * "active": true
     * },
     * {
     * "url": null,
     * "label": "Next &raquo;",
     * "active": false
     * }
     * ],
     * "next_page_url": null,
     * "path": "http://localhost:8080/api/v1/orders/pending",
     * "per_page": 10,
     * "prev_page_url": null,
     * "to": 2,
     * "total": 2
     * },
     * "status": true
     * }
     *
     * @response 500 {
     * "success": false,
     * "message": "Server Error",
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": null
     * }
     *
     * @response 401 {
     * "message": "Unauthenticated.",
     * }
     */
    #[QueryParam("coin", "string", "Coin name. ", required: true, example: 'btc')]
    #[QueryParam("currency", "string", "Currency name. ", required: true, example: 'usdt')]
    #[QueryParam("page", "int", "Page. ", required: false, example: 1)]
    #[QueryParam("limit", "int", "Limit. ", required: false, example: 1)]
    public function getOrderPending(Request $request): JsonResponse
    {
        try {
            $orders = $this->orderService->getOrderPending($request, Auth::id());
            return $this->sendResponse($orders);
        } catch (\Exception $exception) {
            logger($exception);
            return $this->sendError($exception);
        }
    }

    /**
     * All order pending of user
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @response {
     * "data": {
     * "current_page": 1,
     * "data": [
     * {
     * "id": 708,
     * "original_id": null,
     * "user_id": 2,
     * "email": "bot2@gmail.com",
     * "trade_type": "sell",
     * "currency": "usd",
     * "coin": "ltc",
     * "type": "limit",
     * "ioc": null,
     * "quantity": "100.0000000000",
     * "price": "2.0000000000",
     * "executed_quantity": "0.0000000000",
     * "executed_price": "0.0000000000",
     * "base_price": null,
     * "stop_condition": null,
     * "fee": "0.0000000000",
     * "status": "pending",
     * "created_at": 1567150919521,
     * "updated_at": 1567150919521,
     * "total": "200.00000000000000000000"
     * },
     * {
     * "id": 709,
     * "original_id": null,
     * "user_id": 2,
     * "email": "bot2@gmail.com",
     * "trade_type": "sell",
     * "currency": "usd",
     * "coin": "ltc",
     * "type": "limit",
     * "ioc": null,
     * "quantity": "100.0000000000",
     * "price": "2.0000000000",
     * "executed_quantity": "0.0000000000",
     * "executed_price": "0.0000000000",
     * "base_price": null, "stop_condition": null,
     * "fee": "0.0000000000",
     * "status": "pending",
     * "created_at": 1567150919521,
     * "updated_at": 1567150919521,
     * "total": "200.00000000000000000000"
     * }
     * ],
     * "first_page_url": "http://localhost:8080/api/v1/orders/pending?page=1",
     * "from": 1,
     * "last_page": 1,
     * "last_page_url": "http://localhost:8080/api/v1/orders/pending?page=1",
     * "links": [
     * {
     * "url": null,
     * "label": "&laquo; Previous",
     * "active": false
     * },
     * {
     * "url": "http://localhost:8080/api/v1/orders/pending?page=1",
     * "label": "1",
     * "active": true
     * },
     * {
     * "url": null,
     * "label": "Next &raquo;",
     * "active": false
     * }
     * ],
     * "next_page_url": null,
     * "path": "http://localhost:8080/api/v1/orders/pending",
     * "per_page": 10,
     * "prev_page_url": null,
     * "to": 2,
     * "total": 2
     * },
     * "status": true
     * }
     *
     * @response 500 {
     * "success": false,
     * "message": "Server Error",
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": null
     * }
     *
     * @response 401 {
     * "message": "Unauthenticated.",
     * }
     */
    #[QueryParam("coin", "string", "Coin name. ", required: true, example: 'btc')]
    #[QueryParam("currency", "string", "Currency name. ", required: true, example: 'usdt')]
    public function getOrderPendingAll(Request $request): JsonResponse
    {
        try {
            $orders = $this->orderService->getOrderPendingAll($request, Auth::id());
            return $this->sendResponse($orders);
        } catch (\Exception $exception) {
            logger($exception);
            return $this->sendError($exception);
        }
    }

    /**
     * Get transaction recent
     *
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @response {
     * "success": true,
     * "message": null,
     * "dataVersion": "2b4cfde274aa7aa6b37074759abfdcf78396047a",
     * "data": [
     * {
     * "created_at": 1661764070863,
     * "price": "19806.1300000000",
     * "quantity": "0.0009650000",
     * "transaction_type": "sell"
     * },
     * {
     * "created_at": 1661764070863,
     * "price": "19806.1300000000",
     * "quantity": "0.0009650000",
     * "transaction_type": "sell"
     * }, {
     * "created_at": 1661764070863,
     * "price": "19806.1300000000",
     * "quantity": "0.0009650000",
     * "transaction_type": "sell"
     * }
     * ]
     * }
     *
     * @response 500 {
     * "success": false,
     * "message": "Server Error",
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": null
     * }
     *
     * @response 401 {
     * "message": "Unauthenticated.",
     * }
     */
    #[QueryParam("coin", "string", "Coin name. ", required: true, example: 'btc')]
    #[QueryParam("currency", "string", "Currency name. ", required: true, example: 'usdt')]
    #[QueryParam("count", "int", "Count the number of record history. ", required: true, example: 50)]
    public function getRecentTransactions(Request $request): JsonResponse
    {
        try {
            $currency = $request->input('currency');
            $coin = $request->input('coin');
            $count = $request->input('count', Consts::DEFAULT_PER_PAGE);
            $transactions = $this->orderService->getRecentTransactions($currency, $coin, $count);
            return $this->sendResponse($transactions);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function getRecentTradesForPair(Request $request, $pair): JsonResponse
    {
        $pair = explode('_', $pair);
        $coin = isset($pair[0]) ? strtolower($pair[0]) : '';
        $currency = isset($pair[1]) ? strtolower($pair[1]) : '';
        $count = $request->input('count', 500);
        $side = $request->input('type');
        $transactions = $this->orderService->getRecentTradesForPair($currency, $coin, $count, $side);
        return $this->sendResponse($transactions);
    }

    public function getMarketSummary(): JsonResponse
    {
        $data = $this->orderService->getMarketSummary();
        return $this->sendResponse($data);
    }

    public function getContractsSummary(): JsonResponse
    {
        $data = $this->orderService->getContractsSummary();
        return $this->sendResponse($data);
    }

    public function getContractsSpecsSummary(): JsonResponse
    {
        $data = $this->orderService->getContractsSpecsSummary();
        return $this->sendResponse($data);
    }

    /**
     * Get Order
     *
     * @urlParam  currency string currency name Example: btc
     * @urlParam  status string status
     * @queryParam immediately string
     *
     * @param Request $request
     * @param $currency
     * @param $status
     * @return \Illuminate\Http\JsonResponse
     *
     * @response 500 {
     * "success": false,
     * "message": "Server Error",
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": null
     * }
     *
     * @response 401 {
     * "message": "Unauthenticated.",
     * }
     */
    public function getOrders(Request $request, string $currency, string $status): JsonResponse
    {
        $user = $request->user();
        $params = $request->all(['type', 'start_date', 'end_date','limit']);
        $immediately = $request->input('immediately');
        // we have to start transaction in order to read with isolation level read uncomitted
        try {
            $orders = $this->orderService->getOrder($user, $immediately, $currency, $status, $params);
            return $this->sendResponse($orders);
        } catch (\Exception $exception) {
            Log::error($exception);

            return $this->sendError($exception->getMessage());
        }
    }

    /**
     * Get order-book
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @response {
     * "success": true,
     * "message": null,
     * "dataVersion": "2b4cfde274aa7aa6b37074759abfdcf78396047a",
     * "data": {
     * "buy": [
     * {
     * "count": 0,
     * "quantity": "0.0355700000",
     * "price": "19804.1600000000"
     * },
     * {
     * "count": 0,
     * "quantity": "0.0355700000",
     * "price": "19804.1600000000"
     * }
     * ],
     * "sell": [
     * {
     * "count": 0,
     * "quantity": "0.0355700000",
     * "price": "19804.1600000000"
     * },
     * {
     * "count": 0,
     * "quantity": "0.0355700000",
     * "price": "19804.1600000000"
     * }
     * ],
     * "updatedAt": "2022-08-30T03:20:37.982678Z",
     * "meta": {
     * "buy": {
     * "min": "18698.1300000000",
     * "max": "19804.1600000000"
     * },
     * "sell": {
     * "min": "19812.0800000000",
     * "max": "21361.1000000000"
     * },
     * "updated_at": 1661829637983
     * },
     * "currency": "usdt",
     * "coin": "btc",
     * "tickerSize": "0.001"
     * }
     * }
     *
     * @response 500 {
     * "success": false,
     * "message": "Server Error",
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": null
     * }
     *
     * @response 401 {
     * "message": "Unauthenticated.",
     * }
     */
    #[QueryParam("coin", "string", "Coin name", required: true, example: 'btc')]
    #[QueryParam("currency", "string", "Currency name", required: true, example: 'usdt')]
    #[QueryParam("tickerSize", "string", "Ticker size", required: true, example: '0.001')]

    /**
     * Get order-book
     *
     * @OA\Get (
     *     path="/api/v1/orders/order-book",
     *     tags={"Market"},
     *     summary="Order book",
     *     description="Order book",
     *
     *     @OA\Parameter(
     *          description="Coin name",
     *          in="query",
     *          name="coin",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              example="btc"
     *          )
     *      ),
     *      @OA\Parameter(
     *          description="Currency name",
     *          in="query",
     *          name="currency",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              example="usdt"
     *          )
     *      ),
     *     @OA\Parameter(
     *           description="Ticker size",
     *           in="query",
     *           name="tickerSize",
     *           @OA\Schema(
     *               type="string",
     *               example="0.001"
     *           )
     *       ),
     *     @OA\Response(
     *          response=200,
     *          description="Successful response",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", nullable=true, example=null),
     *              @OA\Property(property="dataVersion", type="string", example="2b4cfde274aa7aa6b37074759abfdcf78396047a"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(
     *                      property="buy",
     *                      type="array",
     *                      @OA\Items(
     *                          @OA\Property(property="count", type="integer", example=0),
     *                          @OA\Property(property="quantity", type="string", example="0.0355700000"),
     *                          @OA\Property(property="price", type="string", example="19804.1600000000")
     *                      )
     *                  ),
     *                  @OA\Property(
     *                      property="sell",
     *                      type="array",
     *                      @OA\Items(
     *                          @OA\Property(property="count", type="integer", example=0),
     *                          @OA\Property(property="quantity", type="string", example="0.0355700000"),
     *                          @OA\Property(property="price", type="string", example="19804.1600000000")
     *                      )
     *                  ),
     *                  @OA\Property(property="updatedAt", type="string", format="date-time", example="2022-08-30T03:20:37.982678Z"),
     *                  @OA\Property(
     *                      property="meta",
     *                      type="object",
     *                      @OA\Property(
     *                          property="buy",
     *                          type="object",
     *                          @OA\Property(property="min", type="string", example="18698.1300000000"),
     *                          @OA\Property(property="max", type="string", example="19804.1600000000")
     *                      ),
     *                      @OA\Property(
     *                          property="sell",
     *                          type="object",
     *                          @OA\Property(property="min", type="string", example="19812.0800000000"),
     *                          @OA\Property(property="max", type="string", example="21361.1000000000")
     *                      ),
     *                      @OA\Property(property="updated_at", type="integer", example=1661829637983)
     *                  ),
     *                  @OA\Property(property="currency", type="string", example="usdt"),
     *                  @OA\Property(property="coin", type="string", example="btc"),
     *                  @OA\Property(property="tickerSize", type="string", example="0.001")
     *              )
     *          )
     *      ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="invalid_grant"),
     *             @OA\Property(property="message", type="string", example="auth.failed"),
     *             @OA\Property(property="code", type="integer", example=400)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Server Error"),
     *             @OA\Property(property="dataVersion", type="string", example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"),
     *             @OA\Property(property="data", type="string", example=null)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     security={{ "apiAuth": {} }}
     * )
     */
    public function getOrderBook(Request $request): JsonResponse
    {
        try {
            $currency = $request->input('currency');
            $coin = $request->input('coin');

            $tickerSize = $this->orderService->getMinTickerSize($currency, $coin);
            $orderBook = $this->orderService->getOrderBook($currency, $coin, $tickerSize);
            $orderBook['currency'] = $currency;
            $orderBook['coin'] = $coin;
            $orderBook['tickerSize'] = $tickerSize;

            return $this->sendResponse($orderBook);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    public function getUserOrderBook(Request $request): JsonResponse
    {
        $user = $request->user();
        $currency = $request->input('currency');
        $coin = $request->input('coin');
        $orderBook = $this->orderService->getUserOrderBook($user->id, $currency, $coin);
        return $this->sendResponse($orderBook);
    }

    public function getFee(Request $request): JsonResponse
    {
        $data = $this->orderService->getFee(escapse_string_params($request->all()));
        return $this->sendResponse($data);
    }

    public function getTotalFee(Request $request): JsonResponse
    {
        $data = $this->orderService->getTotalFee(escapse_string_params($request->all()));
        return $this->sendResponse($data);
    }

    /**
     * Get trading histories
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @response {
     * "data": {
     * "current_page": 1,
     * "data": [
     * {
     * "trade_type": "sell",
     * "fee": "0.0400000000",
     * "created_at": 1567154633635,
     * "currency": "btc",
     * "coin": "eos",
     * "price": "5.0000000000",
     * "quantity": "4.0000000000",
     * "amount": "20.0000000000"
     * },
     * {
     * "trade_type": "sell",
     * "fee": "0.0400000000",
     * "created_at": 1567154633635,
     * "currency": "btc",
     * "coin": "eos",
     * "price": "5.0000000000",
     * "quantity": "4.0000000000",
     * "amount": "20.0000000000"
     * }
     * ],
     * "first_page_url": "http://localhost:8080/api/v1/orders/trading-histories?page=1",
     * "from": 1,
     * "last_page": 1,
     * "last_page_url": "http://localhost:8080/api/v1/orders/trading-histories?page=1",
     * "links": [
     * {
     * "url": null,
     * "label": "&laquo; Previous",
     * "active": false
     * },
     * {
     * "url": "http://localhost:8080/api/v1/orders/trading-histories?page=1",
     * "label": "1",
     * "active": true
     * },
     * {
     * "url": null,
     * "label": "Next &raquo;",
     * "active": false
     * }
     * ],
     * "next_page_url": null,
     * "path": "http://localhost:8080/api/v1/orders/trading-histories",
     * "per_page": 10,
     * "prev_page_url": null,
     * "to": 2,
     * "total": 2
     * },
     * "status": true
     * }
     *
     * @response 500 {
     * "success": false,
     * "message": "Server Error",
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": null
     * }
     *
     * @response 401 {
     * "message": "Unauthenticated.",
     * }
     */
    #[QueryParam("page", "int", "Page", required: false, example: 1)]
    #[QueryParam("limit", "int", "Limit", required: false, example: 1)]
    public function getTradingHistories(Request $request): JsonResponse
    {
        try {
            $data = $this->orderService->getTradingHistoriesWithPagination($request->all());
            return $this->sendResponse($data);
        } catch (Exception $exception) {
            logger($exception);
            return $this->sendError($exception->getMessage());
        }
    }

    /**
     * Get order by ID
     *
     * @return JsonResponse
     *
     * @response {
     * "success": true,
     * "message": null,
     * "dataVersion": "5d9b75e3b804d14e55e52c723f30f0eb3d94acce",
     * "data": {
     * "id": 26,
     * "original_id": null,
     * "user_id": 859,
     * "email": "xinday@gmail.com",
     * "trade_type": "buy",
     * "currency": "usdt",
     * "coin": "btc",
     * "type": "limit",
     * "ioc": null,
     * "quantity": "1.0000000000",
     * "price": "16000.0000000000",
     * "executed_quantity": "0.0000000000",
     * "executed_price": "0.0000000000",
     * "base_price": null,
     * "stop_condition": null,
     * "fee": "0.0000000000",
     * "status": "new",
     * "created_at": 1689912382925,
     * "updated_at": 1689912382925,
     * "market_type": 0
     * }
     * }
     *
     * @response 500 {
     * "success": false,
     * "message": "Server Error",
     * "dataVersion": "6e7a7795297cdc4222ecb77463a7e83638d3f33f",
     * "data": null
     * }
     *
     * @response 401 {
     * "message": "Unauthenticated."
     * }
     */
    public function getOrderDetail($id): JsonResponse
    {
        try {
            $data = Order::findOrFail($id);
            if ($data && $data->user_id == Auth::id()) {
                return $this->sendResponse($data);
            }
            return $this->sendError('Error 403 Forbiden', 403);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function getMarketFee(Request $request)
    {
        $coin = $request->coin;
        $currency = $request->currency;
        $data = $this->orderService->getMarketFee($currency, $coin);
        return $this->sendResponse($data);
    }
    /**
     * @OA\Get (
     *     path="/trading/limits",
     *     tags={"Account"},
     *     summary="Account order rate limits (USER_DATA)",
     *     description="Trading limits",
     *     @OA\Parameter(
     *           name="currency",
     *           in="query",
     *           description="Currency of tradding limit.",
     *           @OA\Schema(
     *               type="string",
     *               example="btc"
     *           )
     *       ),
     *     @OA\Parameter(
     *           name="coin",
     *           in="query",
     *           description="Coin of tradding limit.",
     *           @OA\Schema(
     *               type="string",
     *               example="xrp"
     *           )
     *       ),
     *      @OA\Response(
     *      response="200",
     *      description="Successful response",
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(property="success", type="boolean", example=true),
     *          @OA\Property(property="message", type="string", nullable=true, example=null),
     *          @OA\Property(property="dataVersion", type="string", example="dc2daadb3085e1dfa7ee03bbdf2c2267acbcba57"),
     *          @OA\Property(
     *              property="data",
     *              type="object",
     *              @OA\Property(property="current_page", type="integer", example=1),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", example=38),
     *                      @OA\Property(property="currency", type="string", example="usd"),
     *                      @OA\Property(property="coin", type="string", example="sol"),
     *                      @OA\Property(property="sell_limit", type="string", example="10000.0000000000"),
     *                      @OA\Property(property="buy_limit", type="string", example="2.0000000000"),
     *                      @OA\Property(property="days", type="int", example=0),
     *                      @OA\Property(property="created_at", type="integer", example=null),
     *                      @OA\Property(property="updated_at", type="integer", example=null),
     *                  )
     *              ),
     *              @OA\Property(property="first_page_url", type="string", example="http://localhost:8000/trading/limits?page=1"),
     *              @OA\Property(property="from", type="integer", example=1),
     *              @OA\Property(property="last_page", type="integer", example=3),
     *              @OA\Property(property="last_page_url", type="string", example="http://localhost:8000/trading/limits?page=3"),
     *              @OA\Property(
     *                  property="links",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="url", type="string", nullable=true, example=null),
     *                      @OA\Property(property="label", type="string", example="pagination.previous"),
     *                      @OA\Property(property="active", type="boolean", example=false)
     *                  )
     *              ),
     *              @OA\Property(property="next_page_url", type="string", example="http://localhost:8000/trading/limits?page=2"),
     *              @OA\Property(property="path", type="string", example="http://localhost:8000/trading/limits"),
     *              @OA\Property(property="per_page", type="integer", example=6),
     *              @OA\Property(property="prev_page_url", type="string", nullable=true, example=null),
     *              @OA\Property(property="to", type="integer", example=6),
     *              @OA\Property(property="total", type="integer", example=14)
     *          )
     *      )
     *     ),
     *     @OA\Response(
     *        response=500,
     *        description="Server error",
     *        @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Server Error"),
     *              @OA\Property(property="dataVersion", type="string", example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"),
     *              @OA\Property(property="data", type="string", example=null)
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated.")
     *          )
     *      ),
     *      security={{ "apiAuth": {} }}
     * )
     *
     */
    public function getTradingLimits(Request $request)
    {
        $params = $request->all();
        if (!$params) {
            $data = TrandingLimit::paginate(Consts::DEFAULT_PER_PAGE)->appends($request->all());
            return $this->sendResponse($data);
        }
        $data = TrandingLimit::scopeParams(TrandingLimit::query(), $params)->paginate(Consts::DEFAULT_PER_PAGE)->appends($request->all());
        return $this->sendResponse($data);
    }
}
