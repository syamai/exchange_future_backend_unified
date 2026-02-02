<?php

namespace App\Http\Services;

use App\Consts;
use App\Enums\StatusVoucher;
use App\Events\OrderTransactionCreated;
use App\Jobs\CalculateAndRefundReferral;
use App\Jobs\InvalidateUserBalanceCache;
use App\Jobs\ProcessOrder;
use App\Jobs\ProcessOrderRequest;
use App\Jobs\ProcessOrderRequestRedis;
use App\Jobs\SendBalance;
use App\Jobs\SendBalanceLogToWallet;
use App\Jobs\SendOrderEvent;
use App\Jobs\SendOrderList;
use App\Jobs\SendSpotEmailOrderFilled;
use App\Jobs\SendTradeFeeToME;
use App\Jobs\UpdateOrderBook;
use App\Jobs\UpdateUserTransaction;
use App\Mail\SendVoucherForUser;
use App\Models\MarketFeeSetting;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\Price;
use App\Models\User;
use App\Models\UserTradeVolumePerDay;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Utils;
use App\Utils\BigNumber;
use App\Utils\OrderbookUtil;
use App\Services\Buffer\BufferedMatchingService;
use App\Services\Buffer\FlushResult;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrderService
{
    private $userService;
    private $priceService;
    private $precisions;
    private ?BufferedMatchingService $bufferedMatchingService = null;
    private bool $useBufferedWrites = false;

    public function __construct(?BufferedMatchingService $bufferedMatchingService = null)
    {
        $this->userService = new UserService();
        $this->priceService = new PriceService();
        $this->precisions = [];
        $this->useBufferedWrites = env('USE_BUFFERED_WRITES', false);
        if ($this->useBufferedWrites) {
            $this->bufferedMatchingService = $bufferedMatchingService ?? new BufferedMatchingService();
        }
    }

    public function create($input)
    {
        $this->validateOrderInput($input);

        $input['status'] = Consts::ORDER_STATUS_NEW;
        if (array_key_exists('price', $input)) {
            $input['reverse_price'] = BigNumber::new($input['price'])->mul(-1)->toString();
        }
        $input['fee'] = 0;
        $input['created_at'] = Utils::currentMilliseconds();
        $input['updated_at'] = Utils::currentMilliseconds();
        return Order::on('master')->create($input);
    }

    private function validateOrderInput($input): void
    {
        $coinSettings = MasterdataService::getOneTable('coin_settings');
        $coinSetting = $coinSettings->first(function ($value) use ($input) {
            return $value->currency == $input['currency'] && $value->coin == $input['coin'];
        });
        if (!$coinSetting) {
            throw new Exception("Invalid pair: {$input['coin']}/{$input['currency']}");
        }

        $this->validateOrderAttribute($input, 'quantity', $coinSetting->quantity_precision);
        $this->validateMinValue('quantity', $input['quantity'], $coinSetting->minimum_quantity);

        if ($input['type'] == Consts::ORDER_TYPE_LIMIT || $input['type'] == Consts::ORDER_TYPE_STOP_LIMIT) {
            $this->validateOrderAttribute($input, 'price', $coinSetting->price_precision);

            $total = BigNumber::new($input['price'])->mul($input['quantity'])->toString();
            $minAmount = BigNumber::new($coinSetting->minimum_amount)->toString(); // Format 10.0000000 --> 10
            $this->validateMinValue('total', $total, $minAmount);
        }

        if ($input['type'] == Consts::ORDER_TYPE_STOP_LIMIT || $input['type'] == Consts::ORDER_TYPE_STOP_MARKET) {
            $this->validateOrderAttribute($input, 'base_price', $coinSetting->price_precision);
        }
    }

    private function validateOrderAttribute($input, $attribute, $precision): void
    {
        $value = $input[$attribute];

        $precision = BigNumber::new($precision)->toString();

        $this->validateMinValue($attribute, $value, $precision);

        if (!$this->isMultiple($value, $precision)) {
            throw new HttpException(422, __('validation.custom.' . $attribute . '.precision',
                ['attribute' => $attribute, 'precision' => $precision]));
        }
    }

    private function validateMinValue($attribute, $value, $precision): void
    {
        $precision = BigNumber::new($precision)->toString();

        if (BigNumber::new($value)->comp($precision) < 0) {
            throw new HttpException(422,
                __('validation.custom.' . $attribute . '.min', ['attribute' => $attribute, 'min' => $precision]));
        }
    }

    private function isMultiple($price, $base): bool
    {
        $ratio = BigNumber::new($price)->div($base)->toString();
        return $ratio === BigNumber::new(round($ratio))->toString();
    }

    /*private function checkBalanceForNewOrder($input)
    {
        if ($input['type'] != Consts::ORDER_TYPE_LIMIT) {
            return;
        }

        $balance = 0;
        if ($input['trade_type'] == Consts::ORDER_TRADE_TYPE_BUY) {
            $balance = DB::table($input['currency'].'_accounts')
                ->where('id', $input['user_id'])
                ->lockForUpdate()
                ->pluck('available_balance')
                ->first();
            $requireBalance = BigNumber::new($input['price'])->mul($input['quantity']);
            if ($requireBalance->comp($balance) > 0) {
                $params = [
                    'need' => $requireBalance,
                    'have' => $balance
                ];
                throw new HttpException(422,__('messages.insufficient_balance', $params));
            }
        } else {
            $balance = DB::table($input['coin'].'_accounts')
                ->where('id', $input['user_id'])
                ->lockForUpdate()
                ->pluck('available_balance')
                ->first();
            $requireBalance = BigNumber::new($input['price']);
            if ($requireBalance->comp($balance) > 0) {
                $params = [
                    'need' => $input['quantity'],
                    'have' => $balance
                ];
                throw new HttpException(422,__('messages.insufficient_balance', $params));
            }
        }
    }*/

    public function updateBalanceForNewOrder($order): bool
    {
        if ($order->trade_type == Consts::ORDER_TRADE_TYPE_BUY) {
            if ($order->type == Consts::ORDER_TYPE_LIMIT || $order->type == Consts::ORDER_TYPE_STOP_LIMIT) {
                $amount = BigNumber::new($order->price)->mul($order->quantity)->toString();
                $updated = DB::connection('master')->table('spot_' . $order->currency . '_accounts')
                    ->where('id', $order->user_id)
                    ->where('available_balance', '>=', $amount)
                    ->decrement('available_balance', $amount);
                if (!$updated) {
                    return false;
                }
                $this->onUserBalanceChanged($order->user_id, [$order->currency]);
            }
        } else {
            $updated = DB::connection('master')->table('spot_' . $order->coin . '_accounts')
                ->where('id', $order->user_id)
                ->where('available_balance', '>=', BigNumber::new($order->quantity)->toString())
                ->decrement('available_balance', BigNumber::new($order->quantity)->toString());
            if (!$updated) {
                return false;
            }
            $this->onUserBalanceChanged($order->user_id, [$order->coin]);
        }
        return true;
    }

    private function onUserBalanceChanged($userId, $currencies): void
    {
        $disableSocketBot = env('DISABLE_SOCKET_BOT', false);
        if ($disableSocketBot) {
            $user = User::where('id', $userId)->first();
            if ($user && $user->type == 'bot') {
                return;
            }
        }

        // Use async job for cache invalidation and WebSocket events
        // This decouples balance updates from the matching engine for better performance
        $useAsyncBalanceUpdate = env('ASYNC_BALANCE_UPDATE', true);

        if ($useAsyncBalanceUpdate) {
            InvalidateUserBalanceCache::dispatch($userId, $currencies, Consts::TYPE_EXCHANGE_BALANCE)
                ->onQueue(Consts::QUEUE_CACHE);
        } else {
            // Fallback to synchronous update
            SendBalance::dispatchIfNeed($userId, $currencies, Consts::TYPE_EXCHANGE_BALANCE);
        }
    }

    public function cancel($userId, $id): void
    {
        $order = Order::find($id);
        if ($order->user_id != $userId) {
            throw new HttpException(422, __('messages.unauthorize'));
        }


        if ($order->canCancel()) {
        	if (env("PROCESS_ORDER_REQUEST_REDIS", false)) {
        		ProcessOrderRequestRedis::onNewOrderRequestCanceled([
        			'orderId' => $order->id,
					'currency' => $order->currency,
					'coin' => $order->coin
				]);
			} else {
				ProcessOrderRequest::dispatch($order->id, ProcessOrderRequest::CANCEL);
			}
        } else {
            //TODO throw exception
        }
    }

    public function cancelAll($userId, $filter = [], $market_type = 0): void
    {
        $cancelableStatus = [
            Consts::ORDER_STATUS_NEW,
            Consts::ORDER_STATUS_PENDING,
            Consts::ORDER_STATUS_STOPPING,
            Consts::ORDER_STATUS_EXECUTING,
        ];
        $orders = Order::where('user_id', $userId)
            ->whereIn('status', $cancelableStatus)
            ->where($filter)
            ->where('market_type', $market_type)
            // ->lockForUpdate()
            ->get();
        $this->cancelOrders($orders);
    }

    public function cancelByType($userId, $type): void
    {
        $cancelableStatus = [
            Consts::ORDER_STATUS_NEW,
            Consts::ORDER_STATUS_PENDING,
            Consts::ORDER_STATUS_STOPPING,
            Consts::ORDER_STATUS_EXECUTING,
        ];
        $orders = Order::where('user_id', $userId)
            ->whereIn('status', $cancelableStatus)
            ->where('type', $type)
            ->get();

        if (count($orders) === 0) {
            throw new Exception("Not record");
        }
        $this->cancelOrders($orders);
    }

    public function cancelOrders($orders): void
    {
    	$isProcessOrderRedis = env("PROCESS_ORDER_REQUEST_REDIS", false);
        foreach ($orders as $order) {
			if ($isProcessOrderRedis) {
				ProcessOrderRequestRedis::onNewOrderRequestCanceled([
					'orderId' => $order->id,
					'currency' => $order->currency,
					'coin' => $order->coin
				]);
			} else {
				ProcessOrderRequest::dispatch($order->id, ProcessOrderRequest::CANCEL);
			}
        }
    }

    private function calQuantityByCurrentBalance(Order $order, $quantity, $price)
    {
        if (!in_array($order->type, [Consts::ORDER_TYPE_MARKET, Consts::ORDER_TYPE_STOP_MARKET])) {
            return $quantity;
        }
        if ($order->trade_type !== Consts::ORDER_TRADE_TYPE_BUY) {
            return $quantity;
        }
        $availableBalance = DB::connection('master')->table("spot_{$order->currency}_accounts")
            ->where('id', $order->user_id)
            ->lockForUpdate()
            ->pluck('available_balance')
            ->first();
        if (BigNumber::new($availableBalance)->comp(0) <= 0) {
            return $quantity;
        }
        $maxQuantity = BigNumber::new($availableBalance)->div($price);
        if ($maxQuantity->comp($quantity) >= 0) {
            return $quantity;
        }
        return $this->round(
            $maxQuantity,
            $order->coin,
            $order->currency,
            'quantity_precision',
            BigNumber::ROUND_MODE_FLOOR
        );
    }

    public function matchOrders(Order $buyOrder, Order $sellOrder, $isBuyerMaker): ?Order
    {
        $buyPrice = $this->calculateBuyPrice($buyOrder, $sellOrder, $isBuyerMaker);
        $sellPrice = $this->calculateSellPrice($buyOrder, $sellOrder, $isBuyerMaker);
        $buyRemaining = $buyOrder->getRemaining();
        $sellRemaining = $sellOrder->getRemaining();

        // The 'buyQuantity' can be 'buyRemaining' or 'quantity' which calculated by current balance.
        $buyQuantity = $this->calQuantityByCurrentBalance($buyOrder, $buyRemaining, $sellPrice);
        $quantity = BigNumber::new($buyQuantity)->comp($sellRemaining) > 0 ? $sellRemaining : $buyQuantity;

        if (BigNumber::new($buyQuantity)->comp(0) === 0 || !$this->checkBalanceToExecuteOrder($buyOrder, $buyPrice,
                $quantity)) {
            Log::info('Insufficient balance, canceled order: ' . $buyOrder->id);
            $this->cancelOrder($buyOrder);
            // add order to queue for next matching
            ProcessOrder::addOrder($sellOrder);
            $this->sendOrderChangedEvent(Consts::ORDER_EVENT_CANCELED, [$buyOrder]);
            return null;
        }
        if (!$this->checkBalanceToExecuteOrder($sellOrder, $sellPrice, $quantity)) {
            Log::info('Insufficient balance, canceled order: ' . $sellOrder->id);
            $this->cancelOrder($sellOrder);
            // add order to queue for next matching
            ProcessOrder::addOrder($buyOrder);
            $this->sendOrderChangedEvent(Consts::ORDER_EVENT_CANCELED, [$sellOrder]);
            return null;
        }

        $subOrder = null;

        // Calculate remaining quantity between 'buyRemaining' and 'buyQuantity'.
        $diffQuantity = BigNumber::new($buyRemaining)->sub($buyQuantity);
        $resultCompare = BigNumber::new($buyQuantity)->comp($sellRemaining);
        $isCompleteSellOrder = $resultCompare > 0 || ($resultCompare == 0 && $diffQuantity->comp(0) > 0);

        if ($isCompleteSellOrder) {
            $subOrder = $this->matchAndCompleteSellOrder($buyOrder, $sellOrder, $quantity, $isBuyerMaker);
        } elseif (BigNumber::new($buyQuantity)->comp($sellRemaining) == 0) {
            $this->matchAndCompleteBothOrders($buyOrder, $sellOrder, $isBuyerMaker);
        } else {
            $subOrder = $this->matchAndCompleteBuyOrder($buyOrder, $sellOrder, $quantity, $isBuyerMaker);
        }
        if (!$this->checkBalanceAfterExecuteOrder($buyOrder, $buyPrice, $quantity)) {
            throw new HttpException(422, __('messages.insufficient_balance'));
        }
        return $subOrder;
    }

    public function matchEngineOrders(Order $buyOrder, Order $sellOrder, $price, $quantity, $buyFee, $sellFee, $isBuyerMaker)
    {
        $buyRemaining = $buyOrder->getRemaining();
        $sellRemaining = $sellOrder->getRemaining();

        $buyQuantity = $this->calQuantityByCurrentBalance($buyOrder, $buyRemaining, $price);

        if (BigNumber::new($buyQuantity)->comp(0) === 0 || !$this->checkBalanceToExecuteOrder($buyOrder, $price, $quantity)) {
            $this->cancelOrder($buyOrder);
            $this->sendOrderChangedEvent(Consts::ORDER_EVENT_CANCELED, [$buyOrder]);
            throw new Exception('Insufficient balance, canceled order: ' . $buyOrder->id);
        }

        if (!$this->checkBalanceToExecuteOrder($sellOrder, $price, $quantity)) {

            $this->cancelOrder($sellOrder);
            $this->sendOrderChangedEvent(Consts::ORDER_EVENT_CANCELED, [$sellOrder]);
            throw new Exception('Insufficient balance, canceled order: ' . $sellOrder->id);
        }

        $diffQuantity = BigNumber::new($buyRemaining)->sub($quantity);
        $resultCompare = BigNumber::new($quantity)->comp($sellRemaining);
        $isCompleteSellOrder = $resultCompare > 0 || ($resultCompare == 0 && $diffQuantity->comp(0) > 0);

        $spotCalcFee = env('MATCHING_SPOT_FEE_ALLOW', false);
        if ($spotCalcFee) {

            $buyFee = $this->calculateBuyFee($buyOrder, $sellOrder, $quantity, $isBuyerMaker);
            $sellFee = $this->calculateSellFee($buyOrder, $sellOrder, $quantity, $isBuyerMaker);
            $allowFee = self::allowTradingFeeAccount($buyOrder->user_id, $sellOrder->user_id);
            if(!$allowFee->get('buy_spot_trading_fee_allow')) $buyFee = 0;
            if(!$allowFee->get('sell_spot_trading_fee_allow')) $sellFee = 0;

            Log::info("matchAndCompleteSellOrder: buy_spot_trading_fee_allow: [{$buyOrder->user_id}]{$allowFee->get('buy_spot_trading_fee_allow')}, sell_spot_trading_fee_allow: [{$sellOrder->user_id}]{$allowFee->get('sell_spot_trading_fee_allow')}");
        }

		$orderBuySuccess = false;
		$orderSellSuccess = false;
		DB::connection('master')->beginTransaction();
        try {
			$orderTransaction = $this->createTransaction($buyOrder, $sellOrder, $quantity, $buyFee, $sellFee,
				$isBuyerMaker);

			$buyerBalanceChange = $this->getBuyerBalanceChanges($buyOrder, $quantity, $price, $buyFee);
			$sellerBalanceChange = $this->getSellerBalanceChanges($sellOrder, $quantity, $price, $sellFee);

			if ($isCompleteSellOrder) {
				$buyOrderStatus = Consts::ORDER_STATUS_EXECUTING;
				$params = [
					$buyOrder->id,
					$sellOrder->id,
					$buyOrderStatus,
					$price,
					$quantity,
					$buyFee,
					$sellFee,
					'spot_' . $buyOrder->currency . '_accounts',
					'spot_' . $buyOrder->coin . '_accounts',
					$buyOrder->user_id,
					$buyerBalanceChange['currency_balance'],
					$buyerBalanceChange['available_balance'],
					$buyerBalanceChange['coin_balance'],
					$sellOrder->user_id,
					$sellerBalanceChange['currency_balance'],
					$sellerBalanceChange['coin_balance']
				];
				$sqlParams = implode(',', array_fill(0, sizeof($params), '?'));
				DB::connection('master')->update('CALL match_and_complete_sell_order(' . $sqlParams . ')', $params);
				$orderSellSuccess = true;

			} elseif (BigNumber::new($buyQuantity)->comp($sellRemaining) == 0 && BigNumber::new($quantity)->comp($sellRemaining) == 0) {
				$params = [
					$buyOrder->id,
					$sellOrder->id,
					$price,
					$quantity,
					$buyFee,
					$sellFee,
					'spot_' . $buyOrder->currency . '_accounts',
					'spot_' . $buyOrder->coin . '_accounts',
					$buyOrder->user_id,
					$buyerBalanceChange['currency_balance'],
					$buyerBalanceChange['available_balance'],
					$buyerBalanceChange['coin_balance'],
					$sellOrder->user_id,
					$sellerBalanceChange['currency_balance'],
					$sellerBalanceChange['coin_balance']
				];
				$sqlParams = implode(',', array_fill(0, sizeof($params), '?'));
				DB::connection('master')->update('CALL match_and_complete_both_orders(' . $sqlParams . ')', $params);
				$orderBuySuccess = true;
				$orderSellSuccess = true;

			} else {
				$orderBuySuccess = true;
				$buyOrderStatus = Consts::ORDER_STATUS_EXECUTED;
				if (BigNumber::new($buyQuantity)->comp($quantity) > 0) {
					$buyOrderStatus = Consts::ORDER_STATUS_EXECUTING;
					$orderBuySuccess = false;
				}
				$sellOrderStatus = Consts::ORDER_STATUS_EXECUTING;

				$params = [
					$buyOrder->id,
					$sellOrder->id,
					$buyOrderStatus,
					$sellOrderStatus,
					$price,
					$quantity,
					$buyFee,
					$sellFee,
					'spot_' . $buyOrder->currency . '_accounts',
					'spot_' . $buyOrder->coin . '_accounts',
					$buyOrder->user_id,
					$buyerBalanceChange['currency_balance'],
					$buyerBalanceChange['available_balance'],
					$buyerBalanceChange['coin_balance'],
					$sellOrder->user_id,
					$sellerBalanceChange['currency_balance'],
					$sellerBalanceChange['coin_balance'],
				];
				$sqlParams = implode(',', array_fill(0, sizeof($params), '?'));
				DB::connection('master')->update('CALL match_and_complete_buy_order(' . $sqlParams . ')', $params);
			}
			DB::connection('master')->commit();
		} catch (Exception $e) {
			DB::connection('master')->rollBack();
			throw $e;
		}

        $userAutoMatching = env('FAKE_USER_AUTO_MATCHING', 1);

		if (env("SEND_SOCKET_TRADE_PROCCESS", false)) {
			$this->priceService->updatePrice($orderTransaction); // send price
			$buyOrderSuccess = Order::on('master')->find($orderTransaction->buy_order_id);
			$sellOrderSuccess = Order::on('master')->find($orderTransaction->sell_order_id);
			$this->sendUpdateOrderBookEvent(
				Consts::ORDER_BOOK_UPDATE_MATCHED,
				[$buyOrderSuccess, $sellOrderSuccess],
				$orderTransaction->quantity
			);
		}

		if (!$userAutoMatching || $userAutoMatching != $buyOrder->user_id || $userAutoMatching != $sellOrder->user_id) {
			$this->updateTransactionFee($orderTransaction, $buyOrder, $sellOrder, $userAutoMatching);
            $buyer = User::where('id', $buyOrder->user_id)->first();
            $seller = User::where('id', $sellOrder->user_id)->first();
            $buyerBot = $buyer && $buyer->type == 'bot';
            $sellerBot = $seller && $seller->type == 'bot';
			$this->updateUserTransaction($orderTransaction, $buyOrder, $sellOrder, $buyerBalanceChange, $sellerBalanceChange, $buyerBot, $sellerBot);
			if ($spotCalcFee) {
				$this->sendFeeToME($orderTransaction, $buyOrder, $sellOrder, $buyFee, $sellFee);
			}


            if (!$buyerBot || !$sellerBot) {
				CalculateAndRefundReferral::dispatchIfNeed($orderTransaction->id);
				$this->sendMatchingOrderBalanceLogToWallet($orderTransaction);
			}

            // send email notify status order
			if (!$buyerBot) {
				$this->onUserBalanceChanged($buyOrder->user_id, [$buyOrder->currency, $buyOrder->coin]);
				if ($orderBuySuccess ) {
					SendSpotEmailOrderFilled::dispatchIfNeed($buyOrder->id);
				}
			}

            if (!$sellerBot) {
				$this->onUserBalanceChanged($sellOrder->user_id, [$sellOrder->currency, $sellOrder->coin]);
            	if ($orderSellSuccess) {
					SendSpotEmailOrderFilled::dispatchIfNeed($sellOrder->id);
				}
			}
        }

//        if (!$this->checkBalanceAfterExecuteOrder($buyOrder, $price, $quantity)) {
//            throw new HttpException(422, __('messages.insufficient_balance'));
//        }
    }

    /**
     * Match orders with buffering support.
     *
     * This method mirrors matchOrders() but uses BufferedMatchingService
     * for high-performance batch DB writes instead of Stored Procedures.
     *
     * @param Order $buyOrder
     * @param Order $sellOrder
     * @param bool $isBuyerMaker
     * @return Order|null Remaining order for partial fills, or null
     */
    public function matchOrdersWithBuffering(Order $buyOrder, Order $sellOrder, bool $isBuyerMaker): ?Order
    {
        if (!$this->useBufferedWrites || !$this->bufferedMatchingService) {
            throw new Exception('BufferedMatchingService is not enabled. Set USE_BUFFERED_WRITES=true');
        }

        $buyPrice = $this->calculateBuyPrice($buyOrder, $sellOrder, $isBuyerMaker);
        $sellPrice = $this->calculateSellPrice($buyOrder, $sellOrder, $isBuyerMaker);
        $buyRemaining = $buyOrder->getRemaining();
        $sellRemaining = $sellOrder->getRemaining();

        // Calculate quantity based on balance
        $buyQuantity = $this->calQuantityByCurrentBalance($buyOrder, $buyRemaining, $sellPrice);
        $quantity = BigNumber::new($buyQuantity)->comp($sellRemaining) > 0 ? $sellRemaining : $buyQuantity;

        // Check balances
        if (BigNumber::new($buyQuantity)->comp(0) === 0 || !$this->checkBalanceToExecuteOrder($buyOrder, $buyPrice, $quantity)) {
            Log::info('Insufficient balance, canceled order: ' . $buyOrder->id);
            $this->cancelOrder($buyOrder);
            ProcessOrder::addOrder($sellOrder);
            $this->sendOrderChangedEvent(Consts::ORDER_EVENT_CANCELED, [$buyOrder]);
            return null;
        }

        if (!$this->checkBalanceToExecuteOrder($sellOrder, $sellPrice, $quantity)) {
            Log::info('Insufficient balance, canceled order: ' . $sellOrder->id);
            $this->cancelOrder($sellOrder);
            ProcessOrder::addOrder($buyOrder);
            $this->sendOrderChangedEvent(Consts::ORDER_EVENT_CANCELED, [$sellOrder]);
            return null;
        }

        // Calculate fees
        $buyFee = $this->calculateBuyFee($buyOrder, $sellOrder, $quantity, $isBuyerMaker);
        $sellFee = $this->calculateSellFee($buyOrder, $sellOrder, $quantity, $isBuyerMaker);

        // Check fee allowance
        $allowFee = self::allowTradingFeeAccount($buyOrder->user_id, $sellOrder->user_id);
        if (!$allowFee->get('buy_spot_trading_fee_allow')) {
            $buyFee = '0';
        }
        if (!$allowFee->get('sell_spot_trading_fee_allow')) {
            $sellFee = '0';
        }

        // Use execution price (sellPrice for limit order matching)
        $executionPrice = $sellPrice;

        // Buffer the match (no immediate DB write)
        $this->bufferedMatchingService->bufferMatch(
            $buyOrder,
            $sellOrder,
            $executionPrice,
            $quantity,
            $buyFee,
            $sellFee,
            $isBuyerMaker
        );

        // Update order objects in memory (for subsequent processing)
        $buyOrder->executed_quantity = BigNumber::new($buyOrder->executed_quantity ?? '0')
            ->add($quantity)->toString();
        $buyOrder->fee = BigNumber::new($buyOrder->fee ?? '0')->add($buyFee)->toString();

        $sellOrder->executed_quantity = BigNumber::new($sellOrder->executed_quantity ?? '0')
            ->add($quantity)->toString();
        $sellOrder->fee = BigNumber::new($sellOrder->fee ?? '0')->add($sellFee)->toString();

        // Determine remaining order
        $diffQuantity = BigNumber::new($buyRemaining)->sub($buyQuantity);
        $resultCompare = BigNumber::new($buyQuantity)->comp($sellRemaining);
        $isCompleteSellOrder = $resultCompare > 0 || ($resultCompare == 0 && $diffQuantity->comp(0) > 0);

        // Return remaining order for further matching
        if ($isCompleteSellOrder) {
            // Sell order is complete, buy order may have remaining
            $buyNewRemaining = BigNumber::new($buyRemaining)->sub($quantity);
            if ($buyNewRemaining->comp('0') > 0) {
                return $buyOrder; // Buy order has remaining
            }
        } elseif (BigNumber::new($buyQuantity)->comp($sellRemaining) < 0) {
            // Buy order is complete, sell order has remaining
            return $sellOrder;
        }

        // Both orders complete or no remaining
        return null;
    }

    /**
     * Match orders using BufferedMatchingService for high-performance batch writes.
     *
     * This is a lower-level method that buffers DB writes with pre-calculated values.
     * For automatic calculation, use matchOrdersWithBuffering() instead.
     *
     * Performance improvement: 5-10ms/match -> 0.2ms/match
     *
     * @param Order $buyOrder
     * @param Order $sellOrder
     * @param string $price Execution price
     * @param string $quantity Execution quantity
     * @param string $buyFee Buyer fee
     * @param string $sellFee Seller fee
     * @param bool $isBuyerMaker
     * @return array Trade data that was buffered
     */
    public function matchOrdersBuffered(
        Order $buyOrder,
        Order $sellOrder,
        string $price,
        string $quantity,
        string $buyFee,
        string $sellFee,
        bool $isBuyerMaker
    ): array {
        if (!$this->useBufferedWrites || !$this->bufferedMatchingService) {
            throw new Exception('BufferedMatchingService is not enabled. Set USE_BUFFERED_WRITES=true');
        }

        return $this->bufferedMatchingService->bufferMatch(
            $buyOrder,
            $sellOrder,
            $price,
            $quantity,
            $buyFee,
            $sellFee,
            $isBuyerMaker
        );
    }

    /**
     * Flush all buffered writes to the database.
     *
     * This should be called after the matching loop completes to persist all changes.
     *
     * @return FlushResult|null Result of the flush operation, or null if buffering is disabled
     */
    public function flushBufferedWrites(): ?FlushResult
    {
        if (!$this->useBufferedWrites || !$this->bufferedMatchingService) {
            return null;
        }

        return $this->bufferedMatchingService->flush();
    }

    /**
     * Get buffered matching statistics.
     *
     * @return array|null Statistics, or null if buffering is disabled
     */
    public function getBufferedMatchingStats(): ?array
    {
        if (!$this->useBufferedWrites || !$this->bufferedMatchingService) {
            return null;
        }

        return $this->bufferedMatchingService->getStats();
    }

    /**
     * Check if buffered writes are enabled.
     *
     * @return bool
     */
    public function isBufferedWritesEnabled(): bool
    {
        return $this->useBufferedWrites && $this->bufferedMatchingService !== null;
    }

    private function sendFeeToME($orderTransaction, $buyOrder, $sellOrder, $buyFee, $sellFee) {
        //send buy
        if ($orderTransaction->buy_fee > 0) {
            SendTradeFeeToME::dispatchIfNeed([
                'type' => 'withdrawal',
                'data' => [
                    'userId' => $orderTransaction->buyer_id,
                    'coin' => $buyOrder->coin,
                    'amount' => $orderTransaction->buy_fee,
                    'orderId' => $orderTransaction->buy_order_id,
                    'tradeId' => $orderTransaction->id
                ]
            ]);
        }


        //send sell
        if ($orderTransaction->sell_fee > 0) {
            SendTradeFeeToME::dispatchIfNeed([
                'type' => 'withdrawal',
                'data' => [
                    'userId' => $orderTransaction->seller_id,
                    'coin' => $buyOrder->currency,
                    'amount' => $orderTransaction->sell_fee,
                    'orderId' => $orderTransaction->sell_order_id,
                    'tradeId' => $orderTransaction->id
                ]
            ]);
        }
    }

    private function sendMatchingOrderBalanceLogToWallet($orderTransaction)
    {
        if (env('SEND_BALANCE_LOG_TO_WALLET', false)) {
            // send buy
            $amount = BigNumber::new($orderTransaction->price)->mul($orderTransaction->quantity)->toString();
            SendBalanceLogToWallet::dispatch([
                'userId' => $orderTransaction->buyer_id,
                'walletType' => 'SPOT',
                'type' => 'MATCH_ORDER',
                "baseCurrency" => $orderTransaction->currency,
                "baseCurrencyQuantity" => $amount,
                "currency" => $orderTransaction->coin,
                "currencyPrice" => $orderTransaction->price,
                "currencyAmount" => $orderTransaction->quantity,
                "currencyFeeAmount" => $orderTransaction->buy_fee,
                "currencyAmountWithoutFee" => BigNumber::new($orderTransaction->quantity)->sub($orderTransaction->buy_fee)->toString(),
                'date' => Utils::currentMilliseconds()
            ])->onQueue(Consts::QUEUE_BALANCE_WALLET);

            //send sell
            SendBalanceLogToWallet::dispatch([
                'userId' => $orderTransaction->seller_id,
                'walletType' => 'SPOT',
                'type' => 'MATCH_ORDER',
                "baseCurrency" => $orderTransaction->coin,
                "baseCurrencyQuantity" => $orderTransaction->quantity,
                "currency" => $orderTransaction->currency,
                "currencyPrice" => BigNumber::new($orderTransaction->quantity, BigNumber::ROUND_MODE_HALF_UP, 18)->div($amount)->toString(),
                "currencyAmount" => $amount,
                "currencyFeeAmount" => $orderTransaction->sell_fee,
                "currencyAmountWithoutFee" => BigNumber::new($amount)->sub($orderTransaction->sell_fee)->toString(),
                'date' => Utils::currentMilliseconds()
            ])->onQueue(Consts::QUEUE_BALANCE_WALLET);
        }
    }

    private function allowTradingFeeAccount($buyId, $sellId) {
        $accountBuyFeeAllow = User::findOrFail($buyId)->AccountProfileSetting->spot_trading_fee_allow ?? null;
        $accountSellFeeAllow =  User::findOrFail($sellId)->AccountProfileSetting->spot_trading_fee_allow ?? null;

        $allow = collect();
        $allow->put('buy_spot_trading_fee_allow', $accountBuyFeeAllow);
        $allow->put('sell_spot_trading_fee_allow', $accountSellFeeAllow);

        return $allow;
    }

    private function matchAndCompleteSellOrder(
        Order $buyOrder,
        Order $sellOrder,
        $quantityExecuting,
        $isBuyerMaker
    ): Order {
        $buyOrderStatus = Consts::ORDER_STATUS_EXECUTING;

        $price = $this->calculateBuyPrice($buyOrder, $sellOrder, $isBuyerMaker);
        // $quantity = $quantity ?? $sellOrder->getRemaining();
        $quantity = $quantityExecuting;
        $buyFee = $this->calculateBuyFee($buyOrder, $sellOrder, $quantity, $isBuyerMaker);
        $sellFee = $this->calculateSellFee($buyOrder, $sellOrder, $quantity, $isBuyerMaker);

        $allowFee = self::allowTradingFeeAccount($buyOrder->user_id, $sellOrder->user_id);
        if(!$allowFee->get('buy_spot_trading_fee_allow')) $buyFee = 0;
        if(!$allowFee->get('sell_spot_trading_fee_allow')) $sellFee = 0;
        Log::info("matchAndCompleteSellOrder: buy_spot_trading_fee_allow: [{$buyOrder->user_id}]{$allowFee->get('buy_spot_trading_fee_allow')}, sell_spot_trading_fee_allow: [{$sellOrder->user_id}]{$allowFee->get('sell_spot_trading_fee_allow')}");

        $orderTransaction = $this->createTransaction($buyOrder, $sellOrder, $quantity, $buyFee, $sellFee,
            $isBuyerMaker);

        $buyerBalanceChange = $this->getBuyerBalanceChanges($buyOrder, $quantity, $price, $buyFee);
        $sellerBalanceChange = $this->getSellerBalanceChanges($sellOrder, $quantity, $price, $sellFee);

        $params = [
            $buyOrder->id,
            $sellOrder->id,
            $buyOrderStatus,
            $price,
            $quantity,
            $buyFee,
            $sellFee,
            'spot_' . $buyOrder->currency . '_accounts',
            'spot_' . $buyOrder->coin . '_accounts',
            $buyOrder->user_id,
            $buyerBalanceChange['currency_balance'],
            $buyerBalanceChange['available_balance'],
            $buyerBalanceChange['coin_balance'],
            $sellOrder->user_id,
            $sellerBalanceChange['currency_balance'],
            $sellerBalanceChange['coin_balance']
        ];
        $sqlParams = implode(',', array_fill(0, sizeof($params), '?'));
        DB::connection('master')->update('CALL match_and_complete_sell_order(' . $sqlParams . ')', $params);

        $this->onUserBalanceChanged($buyOrder->user_id, [$buyOrder->currency, $buyOrder->coin]);
        $this->onUserBalanceChanged($sellOrder->user_id, [$sellOrder->currency, $sellOrder->coin]);

        $this->updateUserTransaction($orderTransaction, $buyOrder, $sellOrder, $buyerBalanceChange,
            $sellerBalanceChange);

        // $this->priceService->updatePrice($orderTransaction->currency, $orderTransaction->coin);
        $this->updateTransactionFee($orderTransaction, $buyOrder, $sellOrder);

        CalculateAndRefundReferral::dispatchIfNeed($orderTransaction->id);
        $this->sendMatchingOrderBalanceLogToWallet($orderTransaction);

        return $buyOrder;
    }

    private function matchAndCompleteBothOrders(Order $buyOrder, Order $sellOrder, $isBuyerMaker): void
    {
        $price = $this->calculateBuyPrice($buyOrder, $sellOrder, $isBuyerMaker);
        $quantity = $sellOrder->getRemaining();
        $buyFee = $this->calculateBuyFee($buyOrder, $sellOrder, $quantity, $isBuyerMaker);
        $sellFee = $this->calculateSellFee($buyOrder, $sellOrder, $quantity, $isBuyerMaker);

        $allowFee = self::allowTradingFeeAccount($buyOrder->user_id, $sellOrder->user_id);
        if(!$allowFee->get('buy_spot_trading_fee_allow')) $buyFee = 0;
        if(!$allowFee->get('sell_spot_trading_fee_allow')) $sellFee = 0;
        Log::info("matchAndCompleteBothOrders: buy_spot_trading_fee_allow: [{$buyOrder->user_id}]{$allowFee->get('buy_spot_trading_fee_allow')}, sell_spot_trading_fee_allow: [{$sellOrder->user_id}]{$allowFee->get('sell_spot_trading_fee_allow')}");

        $orderTransaction = $this->createTransaction($buyOrder, $sellOrder, $quantity, $buyFee, $sellFee,
            $isBuyerMaker);

        $buyerBalanceChange = $this->getBuyerBalanceChanges($buyOrder, $quantity, $price, $buyFee);
        $sellerBalanceChange = $this->getSellerBalanceChanges($sellOrder, $quantity, $price, $sellFee);

        $params = [
            $buyOrder->id,
            $sellOrder->id,
            $price,
            $quantity,
            $buyFee,
            $sellFee,
            'spot_' . $buyOrder->currency . '_accounts',
            'spot_' . $buyOrder->coin . '_accounts',
            $buyOrder->user_id,
            $buyerBalanceChange['currency_balance'],
            $buyerBalanceChange['available_balance'],
            $buyerBalanceChange['coin_balance'],
            $sellOrder->user_id,
            $sellerBalanceChange['currency_balance'],
            $sellerBalanceChange['coin_balance']
        ];
        $sqlParams = implode(',', array_fill(0, sizeof($params), '?'));
        DB::connection('master')->update('CALL match_and_complete_both_orders(' . $sqlParams . ')', $params);

        $this->onUserBalanceChanged($buyOrder->user_id, [$buyOrder->currency, $buyOrder->coin]);
        $this->onUserBalanceChanged($sellOrder->user_id, [$sellOrder->currency, $sellOrder->coin]);

        $this->updateUserTransaction($orderTransaction, $buyOrder, $sellOrder, $buyerBalanceChange,
            $sellerBalanceChange);

        // $this->priceService->updatePrice($orderTransaction->currency, $orderTransaction->coin);
        $this->updateTransactionFee($orderTransaction, $buyOrder, $sellOrder);

        CalculateAndRefundReferral::dispatchIfNeed($orderTransaction->id);
        $this->sendMatchingOrderBalanceLogToWallet($orderTransaction);
    }

    private function matchAndCompleteBuyOrder(
        Order $buyOrder,
        Order $sellOrder,
        $quantityExecuting,
        $isBuyerMaker
    ): Order {
        // $buyRemaining = BigNumber::new($buyOrder->getRemaining())->sub($quantityExecuting);
        $buyOrderStatus = Consts::ORDER_STATUS_EXECUTED;
        // if ($buyRemaining->comp(0) > 0) {
        //     $buyOrderStatus = Consts::ORDER_STATUS_EXECUTING;
        // }
        $sellOrderStatus = Consts::ORDER_STATUS_EXECUTING;

        $price = $this->calculateBuyPrice($buyOrder, $sellOrder, $isBuyerMaker);
        // $quantity = $quantity ?? $buyOrder->getRemaining();
        $quantity = $quantityExecuting;
        $buyFee = $this->calculateBuyFee($buyOrder, $sellOrder, $quantity, $isBuyerMaker);
        $sellFee = $this->calculateSellFee($buyOrder, $sellOrder, $quantity, $isBuyerMaker);

        $allowFee = self::allowTradingFeeAccount($buyOrder->user_id, $sellOrder->user_id);
        if(!$allowFee->get('buy_spot_trading_fee_allow')) $sellFee = 0;
        if(!$allowFee->get('sell_spot_trading_fee_allow')) $buyFee = 0;
        Log::info("matchAndCompleteBuyOrder: buy_spot_trading_fee_allow: [{$buyOrder->user_id}]{$allowFee->get('buy_spot_trading_fee_allow')}, sell_spot_trading_fee_allow: [{$sellOrder->user_id}]{$allowFee->get('sell_spot_trading_fee_allow')}");

        $orderTransaction = $this->createTransaction($buyOrder, $sellOrder, $quantity, $buyFee, $sellFee,
            $isBuyerMaker);

        $buyerBalanceChange = $this->getBuyerBalanceChanges($buyOrder, $quantity, $price, $buyFee);
        $sellerBalanceChange = $this->getSellerBalanceChanges($sellOrder, $quantity, $price, $sellFee);

        $params = [
            $buyOrder->id,
            $sellOrder->id,
            $buyOrderStatus,
            $sellOrderStatus,
            $price,
            $quantity,
            $buyFee,
            $sellFee,
            'spot_' . $buyOrder->currency . '_accounts',
            'spot_' . $buyOrder->coin . '_accounts',
            $buyOrder->user_id,
            $buyerBalanceChange['currency_balance'],
            $buyerBalanceChange['available_balance'],
            $buyerBalanceChange['coin_balance'],
            $sellOrder->user_id,
            $sellerBalanceChange['currency_balance'],
            $sellerBalanceChange['coin_balance'],
        ];
        $sqlParams = implode(',', array_fill(0, sizeof($params), '?'));
        DB::connection('master')->update('CALL match_and_complete_buy_order(' . $sqlParams . ')', $params);

        $this->onUserBalanceChanged($buyOrder->user_id, [$buyOrder->currency, $buyOrder->coin]);
        $this->onUserBalanceChanged($sellOrder->user_id, [$sellOrder->currency, $sellOrder->coin]);

        $this->updateUserTransaction($orderTransaction, $buyOrder, $sellOrder, $buyerBalanceChange,
            $sellerBalanceChange);

        // $this->priceService->updatePrice($orderTransaction->currency, $orderTransaction->coin);
        $this->updateTransactionFee($orderTransaction, $buyOrder, $sellOrder);

        CalculateAndRefundReferral::dispatchIfNeed($orderTransaction->id);
        $this->sendMatchingOrderBalanceLogToWallet($orderTransaction);

        return $sellOrder;
    }

    public function updateTransactionFee($transaction, $buyOrder, $sellOrder, $userBot = -1): void
    {
        if (!$transaction) {
            Log::error('UpdateFeeAfterMatchOrder. Cannot find transaction.');
            return;
        }

        $transactionFeeService = new TransactionFeeService();
        $transactionFeeService->updateTransactionFee($transaction, $transaction->buyer_id, $transaction->buy_fee,
            $buyOrder);
        $transactionFeeService->updateTransactionFee($transaction, $transaction->seller_id, $transaction->sell_fee,
            $sellOrder);
        if ($buyOrder->user_id != $userBot) {
            $this->onUserBalanceChanged($buyOrder->user_id,
                [Consts::CURRENCY_AMAL, $transaction->currency, $transaction->coin]);
        }
        if ($sellOrder->user_id != $userBot) {
            $this->onUserBalanceChanged($sellOrder->user_id,
                [Consts::CURRENCY_AMAL, $transaction->currency, $transaction->coin]);
        }
    }

    private function updateUserTransaction(
        $orderTransaction,
        $buyOrder,
        $sellOrder,
        $buyerBalanceChange,
        $sellerBalanceChange,
        $buyerBot = false,
        $sellerBot = false
    ): void {
        if (!$buyerBot) {
            UpdateUserTransaction::dispatchIfNeed(
                Consts::USER_TRANSACTION_TYPE_TRADING,
                $orderTransaction->id,
                $buyOrder->user_id,
                $buyOrder->currency,
                $buyerBalanceChange['currency_balance']
            );
            UpdateUserTransaction::dispatchIfNeed(
                Consts::USER_TRANSACTION_TYPE_TRADING,
                $orderTransaction->id,
                $buyOrder->user_id,
                $buyOrder->coin,
                $buyerBalanceChange['coin_balance']
            );
        }

        if (!$sellerBot) {
            UpdateUserTransaction::dispatchIfNeed(
                Consts::USER_TRANSACTION_TYPE_TRADING,
                $orderTransaction->id,
                $sellOrder->user_id,
                $sellOrder->currency,
                $sellerBalanceChange['currency_balance']
            );
            UpdateUserTransaction::dispatchIfNeed(
                Consts::USER_TRANSACTION_TYPE_TRADING,
                $orderTransaction->id,
                $sellOrder->user_id,
                $sellOrder->coin,
                $sellerBalanceChange['coin_balance']
            );
        }
    }

    public function calculateBuyFee($buyOrder, $sellOrder, $quantity, $isBuyerMaker = false)
    {
        $feeType = $this->getFeeType($buyOrder, $sellOrder);
        $feeRate = $this->getFeeRate($buyOrder, $feeType);
        return BigNumber::new($quantity)->mul($feeRate)->toString();
    }

    public function calculateSellFee($buyOrder, $sellOrder, $quantity, $isBuyerMaker = false)
    {
        $feeType = $this->getFeeType($sellOrder, $buyOrder);
        $feeRate = $this->getFeeRate($sellOrder, $feeType);
        $price = $this->calculateSellPrice($buyOrder, $sellOrder, $isBuyerMaker);
        return BigNumber::new($quantity)->mul($price)->mul($feeRate)->toString();
    }

    public function checkEnableFeeByUser($order): bool
    {
        $userId = $order->user_id;
        $coin = $order->coin;
        $currency = $order->currency;

        $email = @User::find($userId)->email;
        $enableFeeRecord = @DB::table('enable_fee_settings')
            ->where('email', $email)
            ->where('currency', $currency)
            ->where('coin', $coin)
            ->first();

        if (!$enableFeeRecord || $enableFeeRecord->enable_fee == Consts::ENABLE_FEE) {
            return true;
        }

        return false;
    }

    public function getMarketFeeSetting(): Collection
    {
        // $res = MasterdataService::getOneTable('market_fee_setting');

        return DB::connection('master')->table('market_fee_setting')->get();
    }

    // Get fee by market_fee_setting table
    public function getFeeRate($order, $feeType)
    {
        if (!$this->checkEnableFeeByUser($order)) {
            return 0;
        }

        $marketFee = $this->getMarketFeeSetting()
            ->filter(function ($value, $key) use ($order) {
                return $value->coin == $order->coin && $value->currency == $order->currency;
            })
            ->first();

        $fee = ($feeType === Consts::FEE_MAKER) ? $marketFee->fee_maker : $marketFee->fee_taker;
        // percent

        return (new BigNumber($fee))->div(100)->toString();
    }

    private function getFeeType($order1, $order2): string
    {
        if ($this->isOrderMaker($order1, $order2)) {
            return Consts::FEE_MAKER;
        }
        return Consts::FEE_TAKER;
    }

    private function isOrderMaker($order1, $order2): bool
    {
        return $order1->created_at < $order2->created_at;
    }

    private function calculateBuyPrice($buyOrder, $sellOrder, $isBuyerMaker)
    {
        if ($this->isLimitOrder($buyOrder) && $this->isLimitOrder($sellOrder)) {
            return $isBuyerMaker ? $buyOrder->price : $sellOrder->price;
        } elseif ($this->isLimitOrder($buyOrder)) {
            return $buyOrder->price;
        } elseif ($this->isLimitOrder($sellOrder)) {
            return $sellOrder->price;
        } else {
            throw new HttpException(422, __('exception.cannot_calculate'));
        }
    }

    private function calculateSellPrice($buyOrder, $sellOrder, $isBuyerMaker)
    {
        if ($this->isLimitOrder($buyOrder) && $this->isLimitOrder($sellOrder)) {
            return $isBuyerMaker ? $buyOrder->price : $sellOrder->price;
        } elseif ($this->isLimitOrder($sellOrder)) {
            return $sellOrder->price;
        } elseif ($this->isLimitOrder($buyOrder)) {
            return $buyOrder->price;
        } else {
            throw new HttpException(422, __('exception.cannot_calculate'));
        }
    }

    private function isLimitOrder($order): bool
    {
        return $order->type == Consts::ORDER_TYPE_LIMIT || $order->type == Consts::ORDER_TYPE_STOP_LIMIT;
    }

    public function createTransaction(
        Order $buyOrder,
        Order $sellOrder,
        $quantity,
        $buyFee,
        $sellFee,
        $isBuyerMaker
    ): OrderTransaction {
        Log::info("create order transaction: " . $buyOrder->id . ' => ' . $sellOrder->id);
        $price = $this->calculateBuyPrice($buyOrder, $sellOrder, $isBuyerMaker);
        $amount = BigNumber::new($price)->mul($quantity)->toString();
        
        $currencyUsdtPrice = '1';
        // if ($buyOrder->currency != Consts::CURRENCY_USDT) {
        //     $currencyUsdtPrice = Price::where('currency', Consts::CURRENCY_USDT)
        //         ->where('coin', $buyOrder->currency)
        //         ->where('created_at', '<=', Utils::currentMilliseconds())
        //         ->orderByDesc('created_at')
        //         ->first()->price ?? '1';
        // }

        $orderTransaction = new OrderTransaction();
        $orderTransaction->buy_order_id = $buyOrder->id;
        $orderTransaction->sell_order_id = $sellOrder->id;
        $orderTransaction->quantity = $quantity;
        $orderTransaction->price = $price;
        $orderTransaction->currency = $buyOrder->currency;
        $orderTransaction->coin = $buyOrder->coin;
        $orderTransaction->amount = $amount;
        //TODO: convertToBtcAmount needs to fix
        $orderTransaction->btc_amount = 0; //$this->convertToBtcAmount($buyOrder->currency, $amount);
        $orderTransaction->status = Consts::ORDER_STATUS_EXECUTED;
        $orderTransaction->created_at = Utils::currentMilliseconds();
        $orderTransaction->executed_date = Carbon::now();
        $orderTransaction->sell_fee = $sellFee;
        $orderTransaction->buy_fee = $buyFee;
        $orderTransaction->buyer_id = $buyOrder->user_id;
        $orderTransaction->buyer_email = $buyOrder->email;
        $orderTransaction->seller_id = $sellOrder->user_id;
        $orderTransaction->seller_email = $sellOrder->email;
        $orderTransaction->maker_email = $buyOrder->created_at < $sellOrder->created_at ? $buyOrder->email : $sellOrder->email;
        $orderTransaction->taker_email = $buyOrder->created_at < $sellOrder->created_at ? $sellOrder->email : $buyOrder->email;
        $orderTransaction->transaction_type = $this->getTransactionType($buyOrder, $sellOrder, $isBuyerMaker);
        $orderTransaction->currency_usdt_price = $currencyUsdtPrice;
        $orderTransaction->setConnection('master');
        $orderTransaction->save();

        $this->updateTradingVolumeUserPerDay($buyOrder->user_id, $quantity, $buyOrder->coin, $buyOrder->currency,
            $amount, $price);
        $this->updateTradingVolumeUserPerDay($sellOrder->user_id, $quantity, $buyOrder->coin, $buyOrder->currency,
            $amount, $price);
        event(new OrderTransactionCreated($orderTransaction, $buyOrder, $sellOrder));

        return $orderTransaction;
    }

    public function updateTradingVolumeUserPerDay($userId, $volume, $coin, $currency, $amount, $price)
    {
        $priceCoin = 1;
        $priceUsdtWithCoin = 1;
        $vouchers = Voucher::whereNull('deleted_at')
            ->where('type', Consts::TYPE_EXCHANGE_BALANCE)
            ->get();

        foreach ($vouchers as $voucher) {
            $voucherUser = UserTradeVolumePerDay::where('voucher_id', $voucher->id)
                ->where('user_id', $userId)
                ->first();

            if ($currency == 'usd' && $coin == 'usdt') {
                $volume = $amount;
                if ($price == 0) {
                    $priceUsdtWithCoin = 0;
                } else {
                    $priceUsdtWithCoin = BigNumber::new(1)->div($price);
                }
            }

            if ($currency == 'usd' && $coin != 'usdt') {
                $pricePairUSDUSDT = $this->priceService->getPrice('usd', 'usdt')->price;
                $volume = $amount;
                if ($pricePairUSDUSDT == 0) {
                    $priceUsdtWithCoin = 0;
                } else {
                    $priceUsdtWithCoin = BigNumber::new(1)->div($pricePairUSDUSDT)->toString();
                }
            }

            if ($currency != 'usd' && $coin != 'usdt') {
                $priceCoin = $price;

                if ($currency != 'usdt') {
                    $priceUsdtWithCoin = $this->priceService->getPrice('usdt', $currency)->price;
                }
            }

            if ($voucherUser) {
                $updateVolume = BigNumber::new($volume)->mul($priceCoin)->mul($priceUsdtWithCoin)->add($voucherUser->volume)->toString();
                $voucherUser->volume = $updateVolume;
                $voucherUser->save();
                $this->availableVoucher($updateVolume, $voucher, $userId);
            } else {
                $newVolume = BigNumber::new($volume)->mul($priceCoin)->mul($priceUsdtWithCoin)->toString();
                $newVoucherUser = new UserTradeVolumePerDay();
                $newVoucherUser->user_id = $userId;
                $newVoucherUser->voucher_id = $voucher->id;
                $newVoucherUser->volume = $newVolume;
                $newVoucherUser->type = Consts::TYPE_EXCHANGE_BALANCE;
                $newVoucherUser->save();
                $this->availableVoucher($newVolume, $voucher, $userId);
            }
        }
    }

    public function availableVoucher($volumeComp, $voucher, $userId, $type = Consts::TYPE_EXCHANGE_BALANCE)
    {
        $userVoucher = UserVoucher::where('user_id', $userId)->where('voucher_id', $voucher->id)->first();
        if (BigNumber::new($volumeComp)->sub($voucher->conditions_use)->comp(0) != -1 && $voucher->type == $type && !$userVoucher) {
            $data = [
                'voucher_id' => $voucher->id,
                'expires_date' => $voucher->expires_date_number ? Carbon::now()->addDays($voucher->expires_date_number)->format('Y-m-d H:i:s') : null,
                'status' => StatusVoucher::AVAILABLE->value,
                'conditions_use_old' => $voucher->conditions_use,
                'amount_old' => $voucher->amount,
                'user_id' => $userId,
            ];
            UserVoucher::create($data);
            $voucher->user_id = $userId;
            $user = User::query()->find($userId);
            $locale = $user->getLocale();
            $title = __('title.notification.reward_center', [], $locale);

            FirebaseNotificationService::send($user->id, $title, '');
            Mail::queue(new SendVoucherForUser($voucher->toArray()));
        }
    }

    private function convertToBtcAmount($currency, $amount)
    {
        if ($currency === 'btc') {
            return $amount;
        }

        $price = $this->priceService->getCurrentPrice($currency, 'btc');

        return BigNumber::new($amount)->div($price->price)->toString();
    }

    private function getTransactionType(Order $buyOrder, Order $sellOrder, $isBuyerMaker): string
    {
        if (!$isBuyerMaker) {
            return Consts::ORDER_TRADE_TYPE_BUY;
        }
        return Consts::ORDER_TRADE_TYPE_SELL;
    }

    private function checkBalanceToExecuteOrder(Order $order, $price, $quantity): bool
    {
        if ($order->trade_type == Consts::ORDER_TRADE_TYPE_BUY) {
            if ($this->isLimitOrder($order)) {
                // We have already deducted available balance
                return true;
            } else {
                $balance = DB::connection('master')->table('spot_' . $order->currency . '_accounts')
                    ->where('id', $order->user_id)
                    // ->lockForUpdate()
                    ->pluck('available_balance')
                    ->first();
                return BigNumber::new($price)->mul($quantity)->comp($balance) <= 0;
            }
        } else {
            // $balance = DB::connection('master')->table($order->coin.'_accounts')
            //     ->where('id', $order->user_id)
            //     ->lockForUpdate()
            //     ->pluck('available_balance')
            //     ->first();
            // return BigNumber::new($quantity)->comp($balance) <= 0;

            // We have already deducted available balance
            return true;
        }
    }

    private function checkBalanceAfterExecuteOrder(Order $order, $price, $quantity): bool
    {
        if ($order->trade_type == Consts::ORDER_TRADE_TYPE_BUY) {
            if ($this->isLimitOrder($order)) {
                return true;
            } else {
                $balance = DB::connection('master')->table('spot_' . $order->currency . '_accounts')
                    ->where('id', $order->user_id)
                    ->pluck('available_balance')
                    ->first();
                return BigNumber::new($balance)->comp(0) >= 0;
            }
        } else {
            return true;
        }
    }

    private function getBuyerBalanceChanges($order, $quantity, $price, $fee): array
    {
        $currencyAmount = BigNumber::new($price)->mul($quantity)->mul(-1)->toString();

        $coinAmount = BigNumber::new($quantity)->sub($fee)->toString();

        $updateData['currency_balance'] = $currencyAmount;

        switch ($order->type) {
            case Consts::ORDER_TYPE_MARKET:
            case Consts::ORDER_TYPE_STOP_MARKET:
                $updateData['available_balance'] = $currencyAmount;
                break;
            case Consts::ORDER_TYPE_LIMIT:
            case Consts::ORDER_TYPE_STOP_LIMIT:
                // e.g.: User A sell 1 btc for price 1000000
                // User B buy 1 btc with price 1100000, when this order is created, we've deducted 1100000 from available balance
                // But when orders matched, real price is 1000000 => we should add (1100000-1000000)*1 to available balance of B
                $deductedAmount = BigNumber::new($order->price)->mul($quantity);
                $diffAmount = BigNumber::new($deductedAmount)->add($currencyAmount)->toString();
                $updateData['available_balance'] = $diffAmount;
                break;
            default:
                throw new HttpException(422, __('exception.unknown_order_type', ['type' => $order->type]));
                break;
        }

        $updateData['coin_balance'] = $coinAmount;
        return $updateData;
    }

    private function getSellerBalanceChanges($order, $quantity, $price, $fee): array
    {
        $amount = BigNumber::new($price)->mul($quantity)->sub($fee)->toString();

        return [
            'coin_usd_amount' => '0',
            'currency_balance' => $amount,
            'available_balance' => $amount,
            'coin_balance' => BigNumber::new($quantity)->mul(-1)->toString()
        ];
    }

    private function round(
        $amount,
        $coin,
        $currency,
        $precisionColumn,
        $roundMode = BigNumber::ROUND_MODE_HALF_UP
    ): string {
        if (empty($this->precisions[$precisionColumn])) {
            $this->precisions[$precisionColumn] = [];
            $coinSettings = MasterdataService::getOneTable('coin_settings');
            foreach ($coinSettings as $setting) {
                $key = $setting->coin . $setting->currency;
                if (array_key_exists($key, $this->precisions[$precisionColumn])) {
                    continue;
                }

                // Calculate:
                // 0.01:            Has 1 number 0 after dot symbol (.)
                // 0.00000001       Has 7 number 0 after dot symbol (.)
                $precision = 0;
                $length = strlen($setting->$precisionColumn);
                for ($i = 2; $i < $length; $i++) {  // "0." has length is 2
                    if ($setting->$precisionColumn[$i] != '0') {
                        $precision = $i - 2;
                        break;
                    }
                }
                $this->precisions[$precisionColumn][$key] = $precision;
            }
        }

        $queryKey = $coin . $currency;
        return BigNumber::round($amount->toString(), $roundMode, $this->precisions[$precisionColumn][$queryKey]);
    }

    public function cancelOrder(Order $order): void
    {
    	$sendEmail = false;
        $shouldUpdateBalance = $order->status !== Consts::ORDER_STATUS_NEW;
        $shouldUpdateOrderbook = $order->status === Consts::ORDER_STATUS_PENDING || $order->status === Consts::ORDER_STATUS_EXECUTING;
        if (BigNumber::new($order->executed_quantity)->comp(0) > 0) {
            $order->status = Consts::ORDER_STATUS_EXECUTED;
			$sendEmail = true;
        } else {
            $order->status = Consts::ORDER_STATUS_CANCELED;
        }
        if ($order->market_type == Consts::ORDER_MARKET_TYPE_TYPE_CONVERT) {
            $order->updated_at = Carbon::now()->timestamp * 1000;
        }
        $order->save();
        if ($shouldUpdateBalance) {
            $this->updateBalanceForCanceledOrder($order);
        }
        // if user cancel a stop order, do not update orderbook because it hasn't added to orderbook yet
        // but we need to update order list


        if ($sendEmail) {
			$user = User::find($order->user_id);
        	if ($user && $user->type != 'bot') {
				SendSpotEmailOrderFilled::dispatchIfNeed($order->id);
			}
		}

        $userAutoMatching = env('FAKE_USER_AUTO_MATCHING', 1);
        if ($userAutoMatching != $order->user_id) {
            if ($shouldUpdateOrderbook) {
                $this->sendUpdateOrderBookEvent(Consts::ORDER_BOOK_UPDATE_CANCELED, [$order]);
            } else {
                $this->sendUpdateOrderListEvent(Consts::ORDER_BOOK_UPDATE_CANCELED, [$order->user_id], $order->currency);
            }
        }
    }

    private function updateBalanceForCanceledOrder($order): void
    {
        $quantity = $order->getRemaining();
        if ($order->trade_type == Consts::ORDER_TRADE_TYPE_BUY) {
            if ($order->type == Consts::ORDER_TYPE_LIMIT || $order->type == Consts::ORDER_TYPE_STOP_LIMIT) {
                $amount = BigNumber::new($order->price)->mul($quantity)->toString();
                DB::connection('master')->table('spot_' . $order->currency . '_accounts')
                    ->where('id', $order->user_id)
                    ->increment('available_balance', $amount);
                $this->onUserBalanceChanged($order->user_id, [$order->currency]);
            }
        } else {
            DB::connection('master')->table('spot_' . $order->coin . '_accounts')
                ->where('id', $order->user_id)
                ->increment('available_balance', BigNumber::new($quantity)->toString());
            $this->onUserBalanceChanged($order->user_id, [$order->coin]);
        }
    }

    public function getMatchableOrders($currency, $coin): Collection
    {
        return DB::table('orders')->where('currency', $currency)
            ->where('coin', $coin)
            ->whereIn('status', [Consts::ORDER_STATUS_PENDING, Consts::ORDER_STATUS_EXECUTING])
            ->orderBy('updated_at', 'asc')
            ->select(['id', 'currency', 'coin', 'updated_at'])
            ->get();
    }

    public function getRecentTransactions($currency, $coin, $count, $userId = null): Collection
    {
        $fakeDataTradeSpot = env("FAKE_DATA_TRADE_SPOT", false);
        if (!$userId && $fakeDataTradeSpot && isset(Consts::FAKE_CURRENCY_COINS[$coin.'_'.$currency])) {
            /*$client = new Client([
                'base_uri' => Consts::DOMAIN_BINANCE_API
            ]);

            $response = $client->get('api/v3/trades', [
                'query' => [
                    'symbol' => Consts::FAKE_CURRENCY_COINS[$coin.'_'.$currency],
                    'limit' => $count
                ],
                'timeout' => 5,
                'connect_timeout' => 5,
            ]);

            $dataTrades = collect(json_decode($response->getBody()->getContents()))->sortByDesc('id');
            if (!$dataTrades->isEmpty()) {
                $result = [];
                foreach ($dataTrades as $trade) {
                    $result[] = (object) [
                        'created_at' => $trade->time,
                        'price' => $trade->price,
                        'quantity' => BigNumber::new($trade->qty)->div(5)->toString(),
                        'transaction_type' => !$trade->isBuyerMaker ? Consts::ORDER_TRADE_TYPE_BUY : Consts::ORDER_TRADE_TYPE_SELL

                    ];
                }
                return collect($result);
            }*/
            $dataTrades = DB::table('prices')
                ->select('created_at', 'price', 'quantity', 'is_buyer')
                ->where('currency', $currency)
                ->where('coin', $coin)
                ->orderBy('id', 'desc')
                ->take($count)
                ->get();
            if (!$dataTrades->isEmpty()) {
                $result = [];
                foreach ($dataTrades as $trade) {
                    $result[] = (object) [
                        'created_at' => $trade->created_at,
                        'price' => $trade->price,
                        'quantity' => $trade->quantity,
                        'transaction_type' => !$trade->is_buyer ? Consts::ORDER_TRADE_TYPE_BUY : Consts::ORDER_TRADE_TYPE_SELL

                    ];
                }
                return collect($result);
            }
        }
        return DB::table('order_transactions')
            ->select('created_at', 'price', 'quantity', 'transaction_type')
            ->where('currency', $currency)
            ->where('coin', $coin)
            ->when($userId, function ($query) use ($userId) {
                return $query->where(function ($query) use ($userId) {
                    $query->where('buyer_id', $userId)->orWhere('seller_id', $userId);
                });
            })
            ->orderBy('id', 'desc')
            ->take($count)
            ->get();
    }

    public function getRecentTradesForPair($currency, $coin, $count, $side = null): Collection
    {
        $count = min($count, Consts::MAX_LIMIT);
        $fakeDataTradeSpot = env("FAKE_DATA_TRADE_SPOT", false);
        if ($fakeDataTradeSpot && isset(Consts::FAKE_CURRENCY_COINS[$coin.'_'.$currency])) {
            return DB::table('prices')
                ->selectRaw("id as trade_id, price, quantity as base_volume, amount as quote_volume, created_at as trade_timestamp, if (is_buyer = 0, 'buy', 'sell') as type")
                ->when(!is_null($side), function ($query) use ($side) {
                    $side = strtolower($side);
                    $isBuyer = $side == Consts::ORDER_TRADE_TYPE_BUY ? 0 : 1;
                    return $query->where('is_buyer', $isBuyer);
                })
                ->where('currency', $currency)
                ->where('coin', $coin)
                ->orderBy('id', 'desc')
                ->take($count)
                ->get();
        }

        return DB::table('order_transactions')
            ->select('id as trade_id', 'price', 'quantity as base_volume', 'amount as quote_volume',
                'created_at as trade_timestamp', 'transaction_type as type')
            ->when(!is_null($side), function ($query) use ($side) {
                return $query->where('transaction_type', $side);
            })
            ->where('currency', $currency)
            ->where('coin', $coin)
            ->orderBy('id', 'desc')
            ->take($count)
            ->get();
    }

    public function getStopOrdersInBatch($processedId, $count)
    {
        return Order::where('status', Consts::ORDER_STATUS_STOPPING)
            ->where('id', '>', $processedId)
            ->orderBy('id', 'asc')
            ->limit($count)
            ->get();
    }

    public function activeStopOrder($order)
    {
        $updatedAt = Utils::currentMilliseconds();
        $order->status = Consts::ORDER_STATUS_PENDING;
        $order->updated_at = $updatedAt;
        $order->save();
        return $order;
    }

    private function queryGetTransactions($params, $userId)
    {
        $oldestId = 0;
        $oldestOrder = Order::where('user_id', $userId)->orderBy('id', 'desc')->skip(1000)->take(1)->get();
        if (count($oldestOrder) > 0) {
            $oldestId = $oldestOrder[0]->id;
        }

        return Order::when($oldestId, function ($query) use ($oldestId) {
            return $query->where('id', '>', $oldestId);
        })
            ->when(!empty($userId), function ($query) use ($params, $userId) {
                return $query->where('orders.user_id', $userId);
            })
            ->when(array_key_exists('start_date', $params), function ($query) use ($params) {
                $startDate = $params["start_date"];
                $endDate = $params["end_date"];
                return $query->whereBetween('orders.updated_at', array($startDate, $endDate));
            })
            ->when(array_key_exists('hide_canceled', $params), function ($query) use ($params) {
                return $query->whereIn('status', [Consts::ORDER_STATUS_EXECUTED, Consts::ORDER_STATUS_EXECUTING]);
            }, function ($query) use ($params) {
                return $query->whereIn('status',
                    [Consts::ORDER_STATUS_EXECUTED, Consts::ORDER_STATUS_CANCELED, Consts::ORDER_STATUS_EXECUTING]);
            })
            ->when(array_key_exists('coin', $params), function ($query) use ($params) {
                return $query->where('coin', $params['coin']);
            })
            ->when(array_key_exists('currency', $params), function ($query) use ($params) {
                return $query->where('currency', $params['currency']);
            })
            ->when(array_key_exists('trade_type', $params), function ($query) use ($params) {
                return $query->where('trade_type', $params['trade_type']);
            })
            ->when(array_key_exists('search_key', $params), function ($query) use ($params) {
                return $query->where('email', 'like', '%' . $params['search_key'] . '%');
            })
            ->when(array_key_exists('market_type', $params), function ($query) use ($params) {
                return $query->where('market_type', Consts::ORDER_MARKET_TYPE_TYPE_CONVERT);
            })
            ->when(!array_key_exists('market_type', $params), function ($query) use ($params) {
                return $query->where('market_type', Consts::ORDER_MARKET_TYPE_TYPE_NORMAL);
            })
            ->selectRaw(
                'id,
                email,
                updated_at,
                coin,
                currency,
                type,
                trade_type,
                executed_price,
                price,
                executed_quantity,
                quantity,
                fee,
                status,
                stop_condition,
                base_price,
                case
                    when executed_quantity = quantity then "filled"
                    when status = "canceled" then "canceled"
                    else "partial_filled"
                end as custom_status',
            )
            ->when(!empty($params['sort']), function ($query) use ($params) {
                if ($params['sort'] != 'total') {
                    if ($params['sort'] == 'coin') {
                        $query->orderBy($params["sort"], $params["sort_type"]);
                        $query->orderBy('currency', $params["sort_type"]);
                    } else {
                        if ($params['sort'] === 'status') {
                            $query->orderBy("custom_status", $params["sort_type"]);
                        } else {
                            $query->orderBy($params["sort"], $params["sort_type"]);
                        }
                    }
                } else {
                    $query->select(DB::raw('*,executed_price * executed_quantity as total'))
                        ->orderBy('total', $params['sort_type']);
                }
            }, function ($query) {
                $query->orderBy('updated_at', 'desc');
            });
    }

    public function getTransactionsWithPagination($params, $userId = null)
    {
        return $this->queryGetTransactions($params, $userId)->paginate(Arr::get($params, 'limit',
            Consts::DEFAULT_PER_PAGE));
    }

    public function getTransactionsForExport($params, $userId)
    {
        return $this->queryGetTransactions($params, $userId)->get();
    }

    public function getTradingHistoriesWithPagination($params)
    {
        return $this->getTradingHistories($params)->paginate(Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE));
    }

    public function getTradingHistoriesForExport($params)
    {
        return $this->getTradingHistories($params)->get();
    }

    public function getMarketSummary(): array
    {
        $result = [];
        $pairs = MasterdataService::getOneTable('coin_settings');
        $minPriceGroups = $this->getMinPriceGroups();
        foreach ($pairs as $pair) {
            if (!$pair->is_enable) {
                continue;
            }
            $symbol = "{$pair->coin}_{$pair->currency}";
            $symbolKey = strtoupper($symbol);
            $currency = $pair->currency;
            $coin = $pair->coin;
            $price24h = $this->priceService->getPriceScopeIn24h($currency, $coin);

            $orderbook = $this->getOrderbook($currency, $coin, $minPriceGroups[$symbol]);
            $lowestAsk = null;
            $highestBid = null;

            if (!$orderbook['sell']->isEmpty()) {
                $firstRow = $orderbook['sell']->first();
                $lowestAsk = $firstRow->price;
                //$result[$symbolKey]['lowestAskQuantity'] = $firstRow->quantity;
            }

            if (!$orderbook['buy']->isEmpty()) {
                $firstRow = $orderbook['buy']->first();
                $highestBid = $firstRow->price;
                //$result[$symbolKey]['highestBidQuantity'] = $firstRow->quantity;
            }

            $result[$symbolKey] = [
                'trading_pairs' => $symbolKey,
                'last_price' => $price24h->current_price,
                'lowest_ask' => $lowestAsk,
                'highest_bid' => $highestBid,
                'base_volume' => $price24h->volume,
                'quote_volume' => $price24h->quote_volume,
                'price_change_percent_24h' => $price24h->changed_percent,
                //'isFrozen' => $pair->is_enable ? 0 : 1,
                'highest_price_24h' => $price24h->max_price,
                'lowest_price_24h' => $price24h->min_price,
            ];


        }

        return $result;
    }

    public function getContractsSummary(): array
    {
        $result = [];
        $pairs = MasterdataService::getOneTable('coin_settings');
        $feeSettings = MasterdataService::getOneTable('market_fee_setting');
        $minPriceGroups = $this->getMinPriceGroups();
        foreach ($pairs as $pair) {
            if (!$pair->is_enable) {
                continue;
            }
            $symbol = "{$pair->coin}_{$pair->currency}";
            $symbolKey = strtoupper($symbol);
            $currency = $pair->currency;
            $coin = $pair->coin;
            $price24h = $this->priceService->getPriceScopeIn24h($currency, $coin);
            $feeSetting = $feeSettings->filter(function ($item) use ($coin, $currency) {
                return $item->coin == $coin && $item->currency == $currency;
            })->first();

            $orderbook = $this->getOrderbook($currency, $coin, $minPriceGroups[$symbol]);
            $usdVolume = $price24h->quote_volume;

            $openInterest = 0;
            $openInterestUsd = 0;
            $ask = null;
            $bid = null;
            $makerFee = 0;
            $takerFee = 0;

            if (!$orderbook['sell']->isEmpty()) {
                $firstRow = $orderbook['sell']->first();
                $ask = $firstRow->price;
                //$result[$symbolKey]['askQuantity'] = $firstRow->quantity;
                $openInterest = $orderbook['sell']->sum('quantity');
                $openInterestUsd += $orderbook['sell']->sum(function ($item) {
                    return $item->price * $item->quantity;
                });

            }
            if (!$orderbook['buy']->isEmpty()) {
                $firstRow = $orderbook['buy']->first();
                $bid = $firstRow->price;
                //$result[$symbolKey]['bidQuantity'] = $firstRow->quantity;
                $openInterest += $orderbook['buy']->sum('quantity');
                $openInterestUsd += $orderbook['buy']->sum(function ($item) {
                    return $item->price * $item->quantity;
                });

            }

            if ($feeSetting) {
                $makerFee = BigNumber::new($feeSetting->fee_maker)->div(100)->toString();
                $takerFee = BigNumber::new($feeSetting->fee_taker)->div(100)->toString();
            }

            $result[$symbolKey] = [
                'ticker_id' => $symbolKey,
                'base_currency' => strtoupper($pair->coin),
                'target_currency' => strtoupper($pair->currency),
                'last_price' => $price24h->current_price,
                'base_volume' => $price24h->volume,
                'USD_volume' => $usdVolume,
                'quote_volume' => $price24h->quote_volume,
                //'isFrozen' => $pair->is_enable ? 0 : 1,
                'bid' => $bid,
                'ask' => $ask,
                'high' => $price24h->max_price,
                'low' => $price24h->min_price,
                'product_type' => 'Perpetual',
                'open_interest' => $openInterest,
                'open_interest_usd' => $openInterestUsd,
                'index_price' => $price24h->previous_price,
                'funding_rate' => 0,
                'next_funding_rate' => 0,
                'next_funding_rate_timestamp' => Carbon::parse(Carbon::now()->format('Y-m-d'))->addDay()->timestamp * 1000,
                'maker_fee' => $makerFee,
                'taker_fee' => $takerFee,
                'base_id' => MasterdataService::getCoinId($coin),
                'quote_id' => MasterdataService::getCoinId($currency),
            ];
        }

        return $result;
    }

    public function getContractsSpecsSummary(): array
    {
        $result = [];
        $pairs = MasterdataService::getOneTable('coin_settings');
        foreach ($pairs as $pair) {
            if (!$pair->is_enable) {
                continue;
            }
            $symbol = "{$pair->coin}_{$pair->currency}";
            $symbolKey = strtoupper($symbol);
            $currency = $pair->currency;
            $coin = $pair->coin;
            $price24h = $this->priceService->getPriceScopeIn24h($currency, $coin);

            $usdVolume = $price24h->quote_volume;

            $openInterest = 0;
            $openInterestUsd = 0;
            $ask = null;
            $bid = null;
            $makerFee = 0;
            $takerFee = 0;

            $result[$symbolKey] = [
                'ticker_id' => $symbolKey,
                'contract_type' => 'Vanilla',
                'contract_price' => $price24h->current_price,
                'contract_price_currency' => strtoupper($pair->currency),
                'base_id' => MasterdataService::getCoinId($coin),
                'quote_id' => MasterdataService::getCoinId($currency),
            ];
        }

        return $result;
    }

    /**
     * Get orderbook for coinmarketcap API
     *
     * @param $currency
     * @param $coin
     * @param $params
     * @return mixed
     */
    public function getCmcOrderbook($currency, $coin, $params): mixed
    {
        $minPriceGroups = $this->getMinPriceGroups();
        $symbol = "{$coin}_{$currency}";
        $mainOrderbooks = collect($this->getOrderbook($currency, $coin, $minPriceGroups[$symbol]))
            ->mapWithKeys(function ($item, $key) {
                switch ($key) {
                    case 'buy':
                        $key = 'bids';
                        $item = collect($item)->map(function ($value) {
                            unset($value->count);
                            return collect($value)->values()->all();
                        });
                        break;
                    case 'sell':
                        $key = 'asks';
                        $item = collect($item)->map(function ($value) {
                            unset($value->count);
                            return collect($value)->values()->all();
                        });
                        break;
                    case 'meta':
                        $key = 'timestamp';
                        $item = $item['updated_at'];
                        break;
                }
                return [$key => $item];
            });
        $mainOrderbooks->forget('updatedAt');

        $depth = Arr::get($params, 'depth', 0);
        $level = Arr::get($params, 'level', 3);

        if ($level == 1) {
            $depth = 1;
        }

        if ($depth) {
            $mainOrderbooks = $mainOrderbooks->map(function ($item, $key) use ($depth) {
                if ($key === 'bids' || $key === 'asks') {
                    $item = collect($item)->slice(0, $depth)
                        ->values()
                        ->all();
                }

                return $item;
            });
        }

        return $mainOrderbooks->all();
    }

    private function getMinPriceGroups(): array
    {
        $groups = MasterdataService::getOneTable('price_groups');
        $result = [];
        foreach ($groups as $group) {
            $symbol = "{$group->coin}_{$group->currency}";
            $result[$symbol] = BigNumber::min($result[$symbol] ?? PHP_INT_MAX, $group->value);
        }
        return $result;
    }

    // Start Orderbook

    public function getOrderBook($currency, $coin, $tickerSize, $autoMatching = false, $userCache = true)
    {
        $price = $this->priceService->getPrice($currency, $coin)->price;
        if ($userCache) {
			$orderBook = $this->getOrderBookFromCache($currency, $coin, $price, $tickerSize);
			if ($orderBook) {
				return $orderBook;
			}
		}

        $matchingJavaAllow = env("MATCHING_JAVA_ALLOW", false);
        if ($matchingJavaAllow) {
            $matchingJavaSymbols = env("MATCHING_JAVA_SYMBOLS", "");
            $allowMatchingJavaSymbols = [];
            if ($matchingJavaSymbols) {
                $expMatchingJavaSymbols = explode(",", strtolower($matchingJavaSymbols));

                foreach ($expMatchingJavaSymbols as $v) {
                    $v = trim($v);
                    if ($v) {
                        $allowMatchingJavaSymbols[$v] = $v;
                    }
                }
            }
            if (!$allowMatchingJavaSymbols || isset($allowMatchingJavaSymbols[strtolower($coin.'_'.$currency)])) {
                return $this->getOrderBookMatchingEngine($currency, $coin, $tickerSize, $price, true);
            }
        }

        $fakeDataTradeSpot = env("FAKE_DATA_TRADE_SPOT", false);
        $orderBook = [];
        $isFakePair = true;
        if ($fakeDataTradeSpot && isset(Consts::FAKE_CURRENCY_COINS[$coin.'_'.$currency])) {
            try {
                $isFakePair = false;
                $keyCacheOrderBook = "GetOrderBookFakeData:$currency:$coin";
                $dataTrade = null;
                if (Cache::has($keyCacheOrderBook)) {
                    $dataTrade = Cache::get($keyCacheOrderBook);
                }

                if (!$dataTrade) {
                    $client = new Client([
                        'base_uri' => Consts::DOMAIN_BINANCE_API
                    ]);

                    $response = $client->get('api/v3/depth', [
                        'query' => [
                            'symbol' => Consts::FAKE_CURRENCY_COINS[$coin.'_'.$currency],
                            'limit' => 500
                        ],
                        'timeout' => 5,
                        'connect_timeout' => 5,
                    ]);

                    $dataTrade = json_decode($response->getBody()->getContents());
                    if (!empty($dataTrade->lastUpdateId)) {
                        Cache::put($keyCacheOrderBook, $dataTrade, 20);
                    }
                }

                if (!empty($dataTrade->lastUpdateId)) {
                    $keyFakeId = 'orderBook' . $currency . $coin. '_fake_id';
                    Cache::forever($keyFakeId, $dataTrade->lastUpdateId);
                    $keyPriceMatch = 'fakePriceOrderMatch' . $currency . $coin;
                    $fakePriceOrderMatch = 0;
                    if (Cache::has($keyPriceMatch)) {
                        $fakePriceOrderMatch = Cache::get($keyPriceMatch);
                    }

                    $buys = [];
                    $sells = [];

                    $buysDb = $this->getOrderGroups($currency, $coin, Consts::ORDER_TRADE_TYPE_BUY, $price, $tickerSize);
                    $sellsDb = $this->getOrderGroups($currency, $coin, Consts::ORDER_TRADE_TYPE_SELL, $price, $tickerSize);

                    $userIdAuto = env('FAKE_USER_AUTO_MATCHING', -1);
                    $orderMarket = null;
                    $orderLimitBuy = null;
                    $orderLimitSell = null;

                    $userAuto = null;

                    if ($userIdAuto > 0) {
                        // get order market
                        $orderMarket = DB::table('orders')
                            ->where('currency', $currency)
                            ->where('coin', $coin)
                            ->whereIn('status', [Consts::ORDER_STATUS_PENDING, Consts::ORDER_STATUS_EXECUTING])
                            ->whereIn('type', [Consts::ORDER_TYPE_MARKET, Consts::ORDER_TYPE_STOP_MARKET/*, Consts::ORDER_TYPE_STOP_LIMIT*/])
                            ->orderBy('updated_at', 'asc')
                            ->select(['id', 'quantity', 'executed_quantity', 'type', 'stop_condition', 'trade_type', 'base_price', 'price', 'currency', 'coin', 'updated_at'])
                            ->get();

                        $orderLimitBuy = DB::table('orders')
                            ->where('user_id', '!=', $userIdAuto)
                            ->where('trade_type', Consts::ORDER_TRADE_TYPE_BUY)
                            ->where('currency', $currency)
                            ->where('coin', $coin)
                            ->where('price', '>=', $fakePriceOrderMatch)
                            ->whereIn('status', [Consts::ORDER_STATUS_PENDING, Consts::ORDER_STATUS_EXECUTING])
                            ->whereIn('type', [Consts::ORDER_TYPE_LIMIT, Consts::ORDER_TYPE_STOP_LIMIT])
                            ->orderBy('updated_at', 'asc')
                            ->select(['id', 'quantity', 'executed_quantity', 'type', 'stop_condition', 'trade_type', 'base_price', 'price', 'currency', 'coin', 'updated_at'])
                            ->get();

                        $orderLimitSell = DB::table('orders')
                            ->where('user_id', '!=', $userIdAuto)
                            ->where('trade_type', Consts::ORDER_TRADE_TYPE_SELL)
                            ->where('currency', $currency)
                            ->where('coin', $coin)
                            ->where('price', '<=', $fakePriceOrderMatch)
                            ->whereIn('status', [Consts::ORDER_STATUS_PENDING, Consts::ORDER_STATUS_EXECUTING])
                            ->whereIn('type', [Consts::ORDER_TYPE_LIMIT, Consts::ORDER_TYPE_STOP_LIMIT])
                            ->orderBy('updated_at', 'asc')
                            ->select(['id', 'quantity', 'executed_quantity', 'type', 'stop_condition', 'trade_type', 'base_price', 'price', 'currency', 'coin', 'updated_at'])
                            ->get();

//                        dd($orderMarket->toArray(), $orderLimitBuy->toArray(), $orderLimitSell->toArray());


                        //$listPrices = [];

                        if ($autoMatching) {
                            $keyAllowMatching = "fakeAutoOrderMatch:$currency:$coin";
                            if ($fakePriceOrderMatch > 0 && !Cache::has($keyAllowMatching) && (!$orderLimitBuy->isEmpty() || !$orderLimitSell->isEmpty() || !$orderMarket->isEmpty())) {
                                //Cache::put($keyAllowMatching, "1", 10);
                                $userAuto = User::find($userIdAuto);
                            }
                        }
                    }

                    foreach ($buysDb as $b) {
                        $pri = BigNumber::round(BigNumber::new($b->price), BigNumber::ROUND_MODE_HALF_UP, 10);
                        if (BigNumber::new($fakePriceOrderMatch)->sub($pri)->toString() < 0 ) {
                            /*if ($userAuto) {
                                $quantityAuto = $b->quantity;
                                do {
                                    $quantityRand = round(floatval(rand($b->quantity/20, $b->quantity/2)/2), 2);
                                    $priceRand = rand(1, 10) * $tickerSize;//floatval(rand(1, 10)/100);
                                    if ($quantityAuto < $quantityRand) {
                                        $quantityRand = $quantityAuto;
                                    }
                                    if ($quantityAuto <= 10) {
                                        $quantityRand = $quantityAuto;
                                    }
                                    if ($quantityRand <= 0) {
                                        continue;
                                    }
                                    $priceMatching = BigNumber::round(BigNumber::new($fakePriceOrderMatch)->add(BigNumber::new($priceRand))->toString(), BigNumber::ROUND_MODE_HALF_UP, 10);
                                    if (BigNumber::new($priceMatching)->sub($pri)->toString() > 0) {
                                        $priceMatching = $pri;
                                    }

                                    $inputSell = [
                                        'user_id' => $userAuto->id,
                                        'email' => $userAuto->email,
                                        "trade_type" => "sell",
                                        "type" => "limit",
                                        "quantity" => $quantityRand,
                                        "price" => $priceMatching,
                                        'reverse_price' => BigNumber::new($pri)->mul(-1)->toString(),
                                        "currency" => $currency,
                                        "coin" => $coin,
                                        'fee' => 0,
                                        'status' => Consts::ORDER_STATUS_NEW,
                                        'created_at' => Utils::currentMilliseconds(),
                                        'updated_at' => Utils::currentMilliseconds(),
                                    ];

                                    try {
                                        $order = Order::on('master')->create($inputSell);
                                        $order->status = Consts::ORDER_STATUS_PENDING;
                                        $this->sendUpdateOrderBookEvent(Consts::ORDER_BOOK_UPDATE_CREATED, [$order]);

                                        $order->save();

                                        if ($order && $order->canMatching()) {
                                            ProcessOrder::onNewOrderCreated($order);
                                        }

                                        $sells[$priceMatching] = (object) [
                                            'count' => 0,
                                            'price' => $priceMatching,
                                            'quantity' => BigNumber::round(BigNumber::new($quantityRand)->mul(-1), BigNumber::ROUND_MODE_HALF_UP, 10)
                                        ];
                                        $quantityAuto = BigNumber::new($quantityAuto)->sub(BigNumber::new($quantityRand))->toString();

                                    } catch (\Exception $e) {
                                        Log::error("Auto create order sell error");
                                        Log::error($e);
                                    }
                                } while($quantityAuto > 0);
                            }*/

                            //matching auto
                            continue;
                        }
                        $buys[$pri] = (object) [
                            'count' => 0,
                            'price' => $pri,
                            'quantity' => BigNumber::round(BigNumber::new($b->quantity)->mul(-1), BigNumber::ROUND_MODE_HALF_UP, 10)
                        ];
                        //$listPrices[$pri] = $pri;
                    }

                    foreach ($sellsDb as $b) {
                        $pri = BigNumber::round(BigNumber::new($b->price), BigNumber::ROUND_MODE_HALF_UP, 10);
                        if (BigNumber::new($fakePriceOrderMatch)->sub($pri)->toString() >= 0) {
                            //matching auto
                            /*if ($userAuto) {
                                $quantityAuto = $b->quantity;
                                do {
                                    $quantityRand = round(floatval(rand($b->quantity/20, $b->quantity/2)/2), 2);
                                    $priceRand = rand(1, 10) * $tickerSize; //floatval(rand(1, 10)/100);
                                    if ($quantityAuto < $quantityRand) {
                                        $quantityRand = $quantityAuto;
                                    }
                                    if ($quantityAuto <= 10) {
                                        $quantityRand = $quantityAuto;
                                    }

                                    if ($quantityRand <= 0) {
                                        continue;
                                    }
                                    $priceMatching = BigNumber::round(BigNumber::new($fakePriceOrderMatch)->sub(BigNumber::new($priceRand))->toString(), BigNumber::ROUND_MODE_HALF_UP, 10);
                                    if (BigNumber::new($priceMatching)->sub($pri)->toString() < 0) {
                                        $priceMatching = $pri;
                                    }
                                    $inputSell = [
                                        'user_id' => $userAuto->id,
                                        'email' => $userAuto->email,
                                        "trade_type" => "buy",
                                        "type" => "limit",
                                        "quantity" => $quantityRand,
                                        "price" => $priceMatching,
                                        'reverse_price' => BigNumber::new($priceMatching)->mul(-1)->toString(),
                                        "currency" => $currency,
                                        "coin" => $coin,
                                        'fee' => 0,
                                        'status' => Consts::ORDER_STATUS_NEW,
                                        'created_at' => Utils::currentMilliseconds(),
                                        'updated_at' => Utils::currentMilliseconds(),
                                    ];

                                    try {
                                        $order = Order::on('master')->create($inputSell);
                                        $order->status = Consts::ORDER_STATUS_PENDING;
                                        $this->sendUpdateOrderBookEvent(Consts::ORDER_BOOK_UPDATE_CREATED, [$order]);
                                        $order->save();

                                        if ($order && $order->canMatching()) {
                                            ProcessOrder::onNewOrderCreated($order);
                                        }

                                        $buys[$priceMatching] = (object) [
                                            'price' => $priceMatching,
                                            'count' => 0,
                                            'quantity' => BigNumber::round(BigNumber::new($quantityRand)->mul(-1), BigNumber::ROUND_MODE_HALF_UP, 10)
                                        ];

                                        $quantityAuto = BigNumber::new($quantityAuto)->sub(BigNumber::new($quantityRand))->toString();

                                    } catch (\Exception $e) {
                                        Log::error("Auto create order buy error");
                                        Log::error($e);
                                    }
                                } while($quantityAuto > 0);
                            }*/
                            continue;
                        }

                        $sells[$pri] = (object) [
                            'price' => $pri,
                            'count' => 0,
                            'quantity' => BigNumber::round(BigNumber::new($b->quantity)->mul(-1), BigNumber::ROUND_MODE_HALF_UP, 10)
                        ];

                        //$listPrices[$pri] = $pri;
                    }

                    foreach ($dataTrade->asks as $ask) {
                        $pri = BigNumber::round(BigNumber::new($ask[0]), BigNumber::ROUND_MODE_HALF_UP, 10);
                        if (BigNumber::new($fakePriceOrderMatch)->sub($pri)->toString() < 0) {
                            if (!isset($sells[$pri])) {
                                $sells[$pri] = (object)[
                                    'count' => 0,
                                    'price' => $pri,
                                    'quantity' => BigNumber::new($ask[1])->div(10)->toString(),
                                ];
                            } else {
                                $sells[$pri]->quantity = BigNumber::new($sells[$pri]->quantity)->add(BigNumber::new($ask[1])->div(10))->toString();
                                if ($sells[$pri]->quantity <= 0) {
                                    $sells[$pri]->quantity = BigNumber::new(floatval(rand(1, 10)/100))->toString();
                                }
                            }
                        } else {
                            if (!isset($buys[$pri])) {
                                $buys[$pri] = (object)[
                                    'count' => 0,
                                    'price' => $pri,
                                    'quantity' => BigNumber::new($ask[1])->div(10)->toString(),
                                ];
                            } else {
                                $buys[$pri]->quantity = BigNumber::new($buys[$pri]->quantity)->add(BigNumber::new($ask[1])->div(10))->toString();
                                if ($buys[$pri]->quantity <= 0) {
                                    $buys[$pri]->quantity = BigNumber::new(floatval(rand(1, 10)/100))->toString();
                                }
                            }
                        }
                    }

                    foreach ($dataTrade->bids as $bid) {
                        $pri = BigNumber::round(BigNumber::new($bid[0]), BigNumber::ROUND_MODE_HALF_UP, 10);
                        if (BigNumber::new($fakePriceOrderMatch)->sub($pri)->toString() >= 0) {
                            if (!isset($buys[$pri])) {
                                $buys[$pri] = (object)[
                                    'price' => $pri,
                                    'count' => 0,
                                    'quantity' => BigNumber::new($bid[1])->div(10)->toString(),
                                ];
                            } else {
                                $buys[$pri]->quantity = BigNumber::new($buys[$pri]->quantity)->add(BigNumber::new($bid[1])->div(10))->toString();
                                if ($buys[$pri]->quantity <= 0) {
                                    $buys[$pri]->quantity = BigNumber::new(floatval(rand(1, 10)/100))->toString();
                                }
                            }
                        } else {
                            if (!isset($sells[$pri])) {
                                $sells[$pri] = (object)[
                                    'price' => $pri,
                                    'count' => 0,
                                    'quantity' => BigNumber::new($bid[1])->div(10)->toString(),
                                ];
                            } else {
                                $sells[$pri]->quantity = BigNumber::new($sells[$pri]->quantity)->add(BigNumber::new($bid[1])->div(10))->toString();
                                if ($sells[$pri]->quantity <= 0) {
                                    $sells[$pri]->quantity = BigNumber::new(floatval(rand(1, 10)/100))->toString();
                                }
                            }
                        }
                    }

                    foreach ($buys as $k => $v) {
                        if ($v->quantity <= 0) {
                            unset($buys[$k]);
                        }
                    }

                    foreach ($sells as $k => $v) {
                        if ($v->quantity <= 0) {
                            unset($sells[$k]);
                        }
                    }
                    //echo "\nlanvo::".time();
                    if ($buys || $sells) {
                        // fake order book null
                        if (!$sells) {
                            foreach ($buys as $k => $v) {
                                $radioPrice = BigNumber::new($fakePriceOrderMatch)->sub(BigNumber::new($k))->toString();
                                if ($radioPrice > 0) {
                                    $priceNew = BigNumber::new($fakePriceOrderMatch)->add($radioPrice)->toString();
                                    $sells[$priceNew] = (object)[
                                        'price' => $priceNew,
                                        'count' => 0,
                                        'quantity' => BigNumber::new($v->quantity)->toString(),
                                    ];
                                }

                            }
                        } else {
                            foreach ($sells as $k => $v) {
                                $radioPrice = BigNumber::new($k)->sub(BigNumber::new($fakePriceOrderMatch))->toString();
                                if ($radioPrice > 0) {
                                    $priceNew = BigNumber::new($fakePriceOrderMatch)->sub($radioPrice)->toString();
                                    $buys[$priceNew] = (object)[
                                        'price' => $priceNew,
                                        'count' => 0,
                                        'quantity' => BigNumber::new($v->quantity)->toString(),
                                    ];
                                }

                            }
                        }

                        $buys = collect(array_values($buys))->sortByDesc('price');
                        $sells = collect(array_values($sells))->sortBy('price');
                        if ($userAuto && !$orderMarket->isEmpty()) {
                            foreach ($orderMarket as $order) {
                                $quantityAuto = BigNumber::new($order->quantity)->sub(BigNumber::new($order->executed_quantity))->toString();
                                $tradeType = $order->trade_type;
                                if (in_array($tradeType, [Consts::ORDER_TRADE_TYPE_SELL, Consts::ORDER_TRADE_TYPE_BUY])) {
                                    $tradeTypeAuto = $tradeType == Consts::ORDER_TRADE_TYPE_SELL ? Consts::ORDER_TRADE_TYPE_BUY : Consts::ORDER_TRADE_TYPE_SELL;

                                    $orderBookAuto = $tradeTypeAuto == Consts::ORDER_TRADE_TYPE_SELL ? $sells : $buys;
                                    $priceMax = $tradeTypeAuto == Consts::ORDER_TRADE_TYPE_SELL ? BigNumber::new($fakePriceOrderMatch)->mul(1.2)->toString() : BigNumber::new($fakePriceOrderMatch)->div(1.2)->toString();
                                    $countOrderCreate = 0;
                                    do {
                                        $topMarketRun = false;
                                        foreach ($orderBookAuto as $v) {
                                            if ($countOrderCreate > 10) {
                                                $topMarketRun = true;
                                                break;
                                            }
                                            if ($quantityAuto <= 0) {
                                                break;
                                            }

                                            if ($tradeTypeAuto == Consts::ORDER_TRADE_TYPE_SELL) {
                                                if (BigNumber::new($v->price)->sub(BigNumber::new($priceMax))->toString() > 0) {
                                                    $topMarketRun = true;
                                                    break;
                                                }

                                            } else {
                                                if (BigNumber::new($v->price)->sub(BigNumber::new($priceMax))->toString() < 0) {
                                                    $topMarketRun = true;
                                                    break;
                                                }
                                            }

                                            if ($order->type == Consts::ORDER_TYPE_STOP_MARKET) {
                                                $flatPrice = BigNumber::new($order->base_price)->sub(BigNumber::new($v->price))->toString();
                                                if ($flatPrice > 0 && $order->stop_condition == Consts::ORDER_STOP_CONDITION_GE || $flatPrice < 0 && $order->stop_condition == Consts::ORDER_STOP_CONDITION_LE) {
                                                    //continue;
                                                    break;
                                                }
                                                $topMarketRun = true;
                                            } else if ($order->type == Consts::ORDER_TYPE_STOP_LIMIT) {
                                                $flatPrice = BigNumber::new($order->price)->sub(BigNumber::new($v->price))->toString();
                                                if ($flatPrice > 0 && $order->stop_condition == Consts::ORDER_STOP_CONDITION_GE || $flatPrice < 0 && $order->stop_condition == Consts::ORDER_STOP_CONDITION_LE) {
                                                    //continue;
                                                    break;
                                                }
                                                $topMarketRun = true;
                                            }
                                            /*$quantityRand = round(floatval(rand($order->quantity/20, $order->quantity/2)/2), 2);
                                            if ($quantityRand <= 0) {
                                                $quantityRand = 1;
                                            }
                                            if ($quantityRand <= $v->quantity) {
                                                $quantityRand = $v->quantity;
                                            }

                                            if ($quantityAuto < $quantityRand) {
                                                $quantityRand = $quantityAuto;
                                            }
//                                            if ($quantityAuto < 10) {
//                                                $quantityRand = $quantityAuto;
//                                            }*/
                                            $quantityRand = $quantityAuto;
                                            //echo "\nlanvo:OrderAuto:".$order->quantity." - ".$v->quantity . " : ".$quantityAuto;
                                            $v->quantity = BigNumber::new($v->quantity)->mul(10)->toString();

                                            if ($quantityRand > $v->quantity) {
                                                $quantityRand = $v->quantity;
                                            }


                                            $priceMatching = BigNumber::new($v->price)->toString();
                                            $inputOrderAuto = [
                                                'user_id' => $userAuto->id,
                                                'email' => $userAuto->email,
                                                "trade_type" => $tradeTypeAuto,
                                                "type" => "limit",
                                                "quantity" => $quantityRand,
                                                "price" => $priceMatching,
                                                'reverse_price' => BigNumber::new($priceMatching)->mul(-1)->toString(),
                                                "currency" => $currency,
                                                "coin" => $coin,
                                                'fee' => 0,
                                                'status' => Consts::ORDER_STATUS_NEW,
                                                'created_at' => Utils::currentMilliseconds(),
                                                'updated_at' => Utils::currentMilliseconds(),
                                            ];

                                            try {
                                                $orderAutoCreate = Order::on('master')->create($inputOrderAuto);
                                                $orderAutoCreate->status = Consts::ORDER_STATUS_PENDING;
                                                $this->sendUpdateOrderBookEvent(Consts::ORDER_BOOK_UPDATE_CREATED, [$orderAutoCreate]);
                                                $orderAutoCreate->save();

                                                if ($orderAutoCreate && $orderAutoCreate->canMatching()) {
                                                    ProcessOrder::onNewOrderCreated($orderAutoCreate);
                                                }

                                                $quantityAuto = BigNumber::new($quantityAuto)->sub(BigNumber::new($quantityRand))->toString();
                                                $countOrderCreate++;

                                            } catch (\Exception $e) {
                                                Log::error("Auto create order buy error");
                                                Log::error($e);
                                            }
                                        }

                                        if (!$topMarketRun && ($order->type == Consts::ORDER_TYPE_STOP_MARKET || $order->type == Consts::ORDER_TYPE_STOP_LIMIT)) {
                                            break;
                                        }

                                        if ($topMarketRun) {
                                            break;
                                        }

                                    } while($quantityAuto > 0);

                                }
                            }
                        }

                        $limitPlaceOrder = env("FAKE_PLACE_ORDER_NUMBER", 3);

                        if ($userAuto && !$orderLimitBuy->isEmpty()) {
                            foreach ($orderLimitBuy as $order) {
                                $pri = BigNumber::round(BigNumber::new($order->price), BigNumber::ROUND_MODE_HALF_UP, 10);
                                if (BigNumber::new($fakePriceOrderMatch)->sub($pri)->toString() <= 0 ) {
                                    $orderBookAuto = $sells;
                                    $quantityAuto = BigNumber::new($order->quantity)->sub(BigNumber::new($order->executed_quantity))->toString();
                                    $countSell = 0;
                                    foreach ($orderBookAuto as $v) {
                                        if ($quantityAuto <= 0 || $countSell >= $limitPlaceOrder) {
                                            break;
                                        }

                                        $flatPrice = BigNumber::new($v->price)->sub(BigNumber::new($pri))->toString();
                                        $flatPriceSell = BigNumber::new($v->price)->sub(BigNumber::new($fakePriceOrderMatch)->mul(1.2))->toString();
                                        if (($flatPrice >= 0 && $countSell > 0) || $flatPriceSell >= 0) {
                                            break;
                                        }

                                        if ($flatPrice >= 0 && $countSell == 0) {
                                            $v->price = $fakePriceOrderMatch;
                                        }

                                        $quantityRand = $quantityAuto;
                                        $v->quantity = BigNumber::new($v->quantity)->mul(10)->toString();
                                        if ($quantityRand > $v->quantity) {
                                            $quantityRand = $v->quantity;
                                        }

                                        $priceMatching = BigNumber::new($v->price)->toString();
                                        //echo "\nlanvo:OrderAuto:".$order->quantity." - ".$v->quantity . " : ".$quantityAuto;

                                        $inputOrderAuto = [
                                            'user_id' => $userAuto->id,
                                            'email' => $userAuto->email,
                                            "trade_type" => "sell",
                                            "type" => "limit",
                                            "quantity" => $quantityRand,
                                            "price" => $priceMatching,
                                            'reverse_price' => BigNumber::new($priceMatching)->mul(-1)->toString(),
                                            "currency" => $currency,
                                            "coin" => $coin,
                                            'fee' => 0,
                                            'status' => Consts::ORDER_STATUS_NEW,
                                            'created_at' => Utils::currentMilliseconds(),
                                            'updated_at' => Utils::currentMilliseconds(),
                                        ];

                                        try {
                                            $orderAutoCreate = Order::on('master')->create($inputOrderAuto);
                                            $orderAutoCreate->status = Consts::ORDER_STATUS_PENDING;
                                            $this->sendUpdateOrderBookEvent(Consts::ORDER_BOOK_UPDATE_CREATED, [$orderAutoCreate]);
                                            $orderAutoCreate->save();

                                            if ($orderAutoCreate && $orderAutoCreate->canMatching()) {
                                                ProcessOrder::onNewOrderCreated($orderAutoCreate);
                                            }

                                            $quantityAuto = BigNumber::new($quantityAuto)->sub(BigNumber::new($quantityRand))->toString();
                                            $countSell++;

                                        } catch (\Exception $e) {
                                            Log::error("Auto create order buy error");
                                            Log::error($e);
                                        }

                                    }
                                }
                            }
                        }

                        if ($userAuto && !$orderLimitSell->isEmpty()) {
                            foreach ($orderLimitSell as $order) {
                                $pri = BigNumber::round(BigNumber::new($order->price), BigNumber::ROUND_MODE_HALF_UP, 10);
                                if (BigNumber::new($fakePriceOrderMatch)->sub($pri)->toString() >= 0) {
                                    $orderBookAuto = $buys;
                                    $quantityAuto = BigNumber::new($order->quantity)->sub(BigNumber::new($order->executed_quantity))->toString();
                                    $countBuy = 0;
                                    foreach ($orderBookAuto as $v) {
                                        if ($quantityAuto <= 0 || $countBuy >= $limitPlaceOrder) {
                                            break;
                                        }
                                        $flatPrice = BigNumber::new($v->price)->sub(BigNumber::new($fakePriceOrderMatch)->mul(1.2))->toString();
                                        $flatPriceBuy = BigNumber::new($v->price)->sub(BigNumber::new($pri))->toString();

                                        if ($flatPrice < 0 && $flatPriceBuy < 0) {
                                            break;
                                        }
                                        $quantityRand = $quantityAuto;
                                        $v->quantity = BigNumber::new($v->quantity)->mul(10)->toString();
                                        if ($quantityRand > $v->quantity) {
                                            $quantityRand = $v->quantity;
                                        }

                                        $priceMatching = BigNumber::new($v->price)->toString();

                                        $inputOrderAuto = [
                                            'user_id' => $userAuto->id,
                                            'email' => $userAuto->email,
                                            "trade_type" => "buy",
                                            "type" => "limit",
                                            "quantity" => $quantityRand,
                                            "price" => $priceMatching,
                                            'reverse_price' => BigNumber::new($priceMatching)->mul(-1)->toString(),
                                            "currency" => $currency,
                                            "coin" => $coin,
                                            'fee' => 0,
                                            'status' => Consts::ORDER_STATUS_NEW,
                                            'created_at' => Utils::currentMilliseconds(),
                                            'updated_at' => Utils::currentMilliseconds(),
                                        ];

                                        try {
                                            $orderAutoCreate = Order::on('master')->create($inputOrderAuto);
                                            $orderAutoCreate->status = Consts::ORDER_STATUS_PENDING;
                                            $this->sendUpdateOrderBookEvent(Consts::ORDER_BOOK_UPDATE_CREATED, [$orderAutoCreate]);
                                            $orderAutoCreate->save();

                                            if ($orderAutoCreate && $orderAutoCreate->canMatching()) {
                                                ProcessOrder::onNewOrderCreated($orderAutoCreate);
                                            }

                                            $quantityAuto = BigNumber::new($quantityAuto)->sub(BigNumber::new($quantityRand))->toString();
                                            $countBuy++;

                                        } catch (\Exception $e) {
                                            Log::error("Auto create order buy error");
                                            Log::error($e);
                                        }

                                    }
                                }
                            }
                        }

                        $buys = collect(array_values($buys->toArray()));
                        $sells = collect(array_values($sells->toArray()));

                        $orderBook = [
                            'buy' => $buys,
                            'sell' => $sells,
                            'updatedAt' => Carbon::now()
                        ];
                    }
                }

            } catch (Exception $e) {
                Log::error("getOrderBook:fake:error");
                Log::error($e);
                throw new Exception('getOrderBook:error');
            }

        }

        if (!$orderBook) {
            if (!$isFakePair) {
                throw new Exception('getOrderBook:ticker:error');
            }
            $orderBook = [
                'buy' => $this->getOrderGroups($currency, $coin, Consts::ORDER_TRADE_TYPE_BUY, $price, $tickerSize),
                'sell' => $this->getOrderGroups($currency, $coin, Consts::ORDER_TRADE_TYPE_SELL, $price, $tickerSize),
                'updatedAt' => Carbon::now()
            ];
        }

        $orderBook['meta'] = $this->createOrderBookMetaData($orderBook);

        $key = $this->getOrderBookKey($currency, $coin, $tickerSize);
        Cache::forever($key, $orderBook);
        return $orderBook;
    }

    public function getOrderBookMatchingEngine($currency, $coin, $tickerSize, $price, $flatList = false)
    {
        try {
            $removeList = env("REMOVE_ORDER_IN_ORDERBOOK_FAIL", true);
            $baseUri = env("DOMAIN_MATCHING_JAVA_API_URL", "");
            if (!$baseUri) {
                throw new Exception('getOrderBook:ME:URL');
            }
            $client = new Client([
                'base_uri' => $baseUri
            ]);
            $url = 'api/spot/orderbook/' . strtoupper($coin.$currency);
            $response = $client->get($url, [
//                'query' => [
//                    'symbol' => Consts::FAKE_CURRENCY_COINS[$coin.'_'.$currency],
//                    'limit' => 500
//                ],
                'timeout' => 5,
                'connect_timeout' => 5,
            ]);
            $buys = [];
            $sells = [];
            $dataOrderBooks = json_decode($response->getBody()->getContents());
            $price = $this->priceService->getPrice($currency, $coin);
            $currPrice = $price->price;

            if ($currPrice < 0) {
                throw new Exception('getOrderBookME:error:load:price');
            }

            if ($dataOrderBooks) {
                foreach ($dataOrderBooks->asks as $ask) {
                    $pri = BigNumber::round(BigNumber::new($ask[0]), BigNumber::ROUND_MODE_HALF_UP, 10);
                    if ($removeList && $flatList) {
                        if (BigNumber::new($pri)->sub($currPrice)->toString() < 0) {
                            continue;
                        }
                    }
                    $sells[$pri] = (object)[
                        'count' => 0,
                        'price' => $pri,
                        'quantity' => BigNumber::new($ask[1])->toString(),
                    ];
                }

                foreach ($dataOrderBooks->bids as $bid) {
                    $pri = BigNumber::round(BigNumber::new($bid[0]), BigNumber::ROUND_MODE_HALF_UP, 10);
                    if ($removeList && $flatList) {
                        if (BigNumber::new($pri)->sub($currPrice)->toString() > 0) {
                            continue;
                        }
                    }
                    $buys[$pri] = (object)[
                        'price' => $pri,
                        'count' => 0,
                        'quantity' => BigNumber::new($bid[1])->toString(),
                    ];
                }
                $buys = collect(array_values($buys))->sortByDesc('price');
                $sells = collect(array_values($sells))->sortByDesc('price');
            }

            $orderBook = [
                'buy' => $buys,
                'sell' => $sells,
                'updatedAt' => Carbon::now()
            ];

            if ($flatList) {
                $orderBook['meta'] = $this->createOrderBookMetaData($orderBook);
                $key = $this->getOrderBookKey($currency, $coin, $tickerSize);
                //Cache::forever($key, $orderBook);
				Cache::put($key, $orderBook, PriceService::PRICE_CACHE_LIVE_TIME);
            }

            return $orderBook;
        } catch (Exception $e) {
            Log::error($e);
            throw new Exception('getOrderBookME:error');
        }
    }

    private function createOrderBookMetaData($orderBook): array
    {
        $minBuyPrice = 0;
        $maxBuyPrice = PHP_INT_MAX;
        $subOrderBook = $orderBook['buy'];
        if ($subOrderBook->count() >= Consts::MAX_ORDER_BOOK_SIZE) {
            $minBuyPrice = $subOrderBook->last()->price;
            $maxBuyPrice = $subOrderBook->first()->price;
        }

        $minSellPrice = 0;
        $maxSellPrice = PHP_INT_MAX;
        $subOrderBook = $orderBook['sell'];
        if ($subOrderBook->count() >= Consts::MAX_ORDER_BOOK_SIZE) {
            $minSellPrice = $subOrderBook->first()->price;
            $maxSellPrice = $subOrderBook->last()->price;
        }

        return [
            'buy' => ['min' => $minBuyPrice, 'max' => $maxBuyPrice],
            'sell' => ['min' => $minSellPrice, 'max' => $maxSellPrice],
            'updated_at' => Utils::currentMilliseconds(),
        ];
    }

    private function getOrderBookFromCache($currency, $coin, $currentPrice, $tickerSize)
    {
        $key = OrderService::getOrderBookKey($currency, $coin, $tickerSize);
        if (!Cache::has($key)) {
            return null;
        }

        // if ($this->shouldReloadOrderBook($currency, $coin, $tickerSize)) {
        //     return null;
        // }

        return Cache::get($key);
    }

    public function shouldReloadOrderBook($currency, $coin, $tickerSize): bool
    {
        $key = OrderService::getOrderBookKey($currency, $coin, $tickerSize);
        if (!Cache::has($key)) {
            return true;
        }

        $orderBook = Cache::get($key);
        $meta = $orderBook['meta'];
        if ($orderBook['buy'] && ($orderBook['buy']->count() <= Consts::ORDER_BOOK_SIZE || $meta['buy']['min'] > 0)) {
            return true;
        }
        if ($orderBook['sell'] && ($orderBook['sell']->count() <= Consts::ORDER_BOOK_SIZE || $meta['sell']['max'] < PHP_INT_MAX)) {
            return true;
        }
        return false;
    }

    public static function getOrderBookKey($currency, $coin, $tickerSize): string
    {
        return 'orderBook' . $currency . $coin . BigNumber::new($tickerSize)->toString();
    }

    private function getOrderGroups($currency, $coin, $tradeType, $price, $tickerSize)
    {
        $query = $this->createOrderGroupQuery($currency, $coin, $tradeType, $price, $tickerSize);
        $query = $this->createOrderGroupCondition($query, $tradeType, $price, $tickerSize);
        return $query->get();
    }

    public function getOrderGroup($currency, $coin, $tradeType, $price, $tickerSize)
    {
        $query = $this->createOrderGroupQuery($currency, $coin, $tradeType, $price, $tickerSize);
        $query->where('price', $price);
        return $query->first();
    }

    private function createOrderGroupQuery($currency, $coin, $tradeType, $price, $tickerSize)
    {
        return DB::connection('master')->table('orderbooks')
            ->selectRaw('count, quantity, price')
            ->where('trade_type', $tradeType)
            ->where('currency', $currency)
            ->where('coin', $coin)
            ->where('ticker', $tickerSize)
            ->where('quantity', '>', 0);
    }

    private function createOrderGroupCondition($query, $tradeType, $price, $tickerSize)
    {
        if ($tradeType == Consts::ORDER_TRADE_TYPE_BUY) {
            $query->orderBy('price', 'desc');
//                ->take(Consts::MAX_ORDER_BOOK_SIZE);
        } else {
            $query->orderBy('price', 'asc');
//                ->take(Consts::MAX_ORDER_BOOK_SIZE);
        }
        return $query;
    }
    // End Orderbook

    // start user's orderbook
    public function getUserOrderBook($userId, $currency, $coin)
    {
        $tickerSize = $this->userService->getOrderBookPriceGroup($userId, $currency, $coin);
        $orderBook = $this->getUserOrderbookFromCache($userId, $currency, $coin, $tickerSize);
        if ($orderBook) {
            return $orderBook;
        }

        $buyGroups = $this->getUserOrderGroups($userId, $currency, $coin, Consts::ORDER_TRADE_TYPE_BUY, $tickerSize);
        $sellGroups = $this->getUserOrderGroups($userId, $currency, $coin, Consts::ORDER_TRADE_TYPE_SELL, $tickerSize);
        $stopBuyGroups = collect([]);
        $stopSellGroups = collect([]);
        $orderBook = [
            'buy' => $buyGroups,
            'sell' => $sellGroups,
            'stop_buy' => $stopBuyGroups,
            'stop_sell' => $stopSellGroups
        ];
        $orderBook['meta'] = $this->createOrderBookMetaData($orderBook);

        $key = $this->getUserOrderbookKey($userId, $currency, $coin, $tickerSize);
        Cache::forever($key, $orderBook);

        return $orderBook;
    }

    private function getUserOrderbookFromCache($userId, $currency, $coin, $tickerSize)
    {
        $key = OrderService::getUserOrderbookKey($userId, $currency, $coin, $tickerSize);
        if (!Cache::has($key)) {
            return null;
        }

        return Cache::get($key);
    }

    /**
     * @param $request
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|null
     * @throws \Throwable
     */
    public function createOrder($request
    ): \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Builder|null {
        $order = null;
        DB::connection('master')->transaction(function () use ($request, &$order) {
            $user = $request->user();
            $input = $request->all();
            $input['user_id'] = $user->id;
            $input['email'] = $user->email;

            $order = $this->create($input);
        }, 3);

		if (env("PROCESS_ORDER_REQUEST_REDIS", false)) {
			ProcessOrderRequestRedis::onNewOrderRequestCreated([
				'orderId' => $order->id,
				'currency' => $order->currency,
				'coin' => $order->coin
			]);
		} else {
			ProcessOrderRequest::dispatch($order->id, ProcessOrderRequest::CREATE);
		}
        $result = Order::on('master')->find($order->id);
        return $result;
    }

    public static function getUserOrderbookKey($userId, $currency, $coin, $tickerSize)
    {
        return 'userOrderBook' . $userId . $currency . $coin . BigNumber::new($tickerSize)->toString();
    }

    private function getUserOrderGroups($userId, $currency, $coin, $tradeType, $tickerSize)
    {
        $orderBy = $tradeType == Consts::ORDER_TRADE_TYPE_BUY ? 'desc' : 'asc';
        return DB::connection('master')->table('user_orderbooks')
            ->selectRaw('count, quantity, price')
            ->where('user_id', $userId)
            ->where('trade_type', $tradeType)
            ->where('currency', $currency)
            ->where('coin', $coin)
            ->where('ticker', $tickerSize)
            ->where('count', '>', 0)
            ->orderBy('price', $orderBy)
            ->take(Consts::MAX_ORDER_BOOK_SIZE)
            ->get();
    }

    // end user's orderbook

    public function updateOrderbookGroup($row, $tickerSizes)
    {
        $tickerSize1 = $tickerSizes[0];
        $tickerSize2 = $tickerSizes[1];
        $price1 = OrderbookUtil::getPriceGroup($row->trade_type, $row->price, $tickerSize1);
        $price2 = OrderbookUtil::getPriceGroup($row->trade_type, $row->price, $tickerSize2);
        $prices = [$price1, $price2];

        $tickerSize3 = -1;
        $price3 = -1;
        $tickerSize4 = -1;
        $price4 = -1;
        if (count($tickerSizes) >= 3) {
            $tickerSize3 = $tickerSizes[2];
            $price3 = OrderbookUtil::getPriceGroup($row->trade_type, $row->price, $tickerSize3);
            $prices[] = $price3;
        }
        if (count($tickerSizes) >= 4) {
            $tickerSize4 = $tickerSizes[3];
            $price4 = OrderbookUtil::getPriceGroup($row->trade_type, $row->price, $tickerSize4);
            $prices[] = $price4;
        }

        $tradeType = $row->trade_type;
        $currency = $row->currency;
        $coin = $row->coin;
        $quantity = $row->quantity;
        $count = 0; //$row->count;

        $params = [
            $tradeType,
            $currency,
            $coin,
            $quantity,
            $count,
            $price1,
            $tickerSize1,
            $price2,
            $tickerSize2,
            $price3,
            $tickerSize3,
            $price4,
            $tickerSize4
        ];

        $sqlParams = implode(',', array_fill(0, sizeof($params), '?'));
        DB::connection('master')->update('CALL update_orderbook_groups(' . $sqlParams . ')', $params);
        if (BigNumber::new($quantity)->comp(0) < 0) {
            $count = count($prices);
            // In order to reduce deadlock, we don't delete first group
            for ($i = 1; $i < $count; $i++) {
                DB::connection('master')
                    ->table('orderbooks')
                    ->where('trade_type', $tradeType)
                    ->where('currency', $currency)
                    ->where('coin', $coin)
                    ->where('price', $prices[$i])
                    ->where('ticker', $tickerSizes[$i])
                    ->where('quantity', 0)
                    ->delete();
            }
        }
    }

    public function sendUpdateOrderBookEvent($action, $orders, $quantity = null)
    {
        $connection = Consts::CONNECTION_SOCKET;
        $currency = $orders[0]->currency;
        $coin = $orders[0]->coin;

        $rows = $this->getRowChanged($action, $orders, $quantity);
        $job = UpdateOrderBook::dispatchIfNeed($action, $rows, $currency, $coin);
		if (env("PROCESS_ORDER_REQUEST_REDIS", false)) {
			if ($job && $connection) {
				$job->onConnection($connection);
			}
		}

        $this->sendUpdateOrderListEvent($action, collect($orders)->pluck('user_id')->unique(), $currency, $connection);
        if ($action == Consts::ORDER_BOOK_UPDATE_CREATED) {
            $this->sendOrderChangedEvent(Consts::ORDER_EVENT_CREATED, $orders);
        } elseif ($action == Consts::ORDER_BOOK_UPDATE_MATCHED) {
            $this->sendOrderChangedEvent(Consts::ORDER_EVENT_MATCHED, $orders);
        } else { // canceled
            // call sendOrderChangedEvent directly when order is canceled
        }
    }

    private function getRowChanged($action, $orders, $quantity)
    {
        return collect($orders)
            ->filter(function ($item) use ($action) {
                if (!$item->price) {
                    return false;
                }
                if ($action == Consts::ORDER_BOOK_UPDATE_CREATED && $item->status != Consts::ORDER_STATUS_PENDING) {
                    return false;
                }
                return true;
            })
            ->map(function ($item) use ($action, $quantity) {
                $row = [
                    'currency' => $item->currency,
                    'coin' => $item->coin,
                    'trade_type' => $item->trade_type,
                    'price' => $item->price,
                ];
                switch ($action) {
                    case Consts::ORDER_BOOK_UPDATE_CREATED:
                    case Consts::ORDER_BOOK_UPDATE_ACTIVATED:
                        $row['quantity'] = BigNumber::new($item->quantity)->toString();
                        break;
                    case Consts::ORDER_BOOK_UPDATE_CANCELED:
                        $row['quantity'] = BigNumber::new($item->getRemaining())->mul(-1)->toString();
                        break;
                    case Consts::ORDER_BOOK_UPDATE_MATCHED:
                        $row['quantity'] = BigNumber::new($quantity)->mul(-1)->toString();
                        break;
                    default:
                        throw new Exception('Unknonw update orderbook action: ' . $action);
                }

                return (object)$row;
            });
    }

    private function sendUpdateOrderListEvent(
        $action,
        $userIds,
        $currency,
        $connection = Consts::CONNECTION_SOCKET
    ): void {
		$disableSocketBot = env('DISABLE_SOCKET_BOT', false);
        foreach ($userIds as $userId) {
			if ($disableSocketBot) {
				$user = User::where('id', $userId)->first();
				if ($user && $user->type == 'bot') {
					continue;
				}
			}
            SendOrderList::dispatchIfNeed($userId, $currency, $action);
        }
    }

    public function sendOrderChangedEvent($action, $orders, $userId = null, $message = null)
    {
		$disableSocketBot = env('DISABLE_SOCKET_BOT', false);
        if ($orders) {
            foreach ($orders as $order) {
            	if ($disableSocketBot) {
					$user = User::where('id', $order->user_id)->first();
					if ($user && $user->type == 'bot') {
						continue;
					}
				}
                SendOrderEvent::dispatchIfNeed($order->user_id, $order, $action, $message);
            }
        } else {
			//check order bot send socket
			if ($disableSocketBot) {
				$user = User::where('id', $userId)->first();
				if ($user && $user->type == 'bot') {
					return;
				}
			}
			SendOrderEvent::dispatchIfNeed($userId, null, $action, $message);

        }
    }

    public static function getBuyerReferredFee($userIds, $startDate, $endDate)
    {
        $coins = MasterdataService::getCurrenciesAndCoins();
        $selectStatements = [];
        foreach ($coins as $coin) {
            $selectStatements[] = "SUM(IF(coin='{$coin}',buy_fee,0)) as {$coin}_fee";
        }
        return OrderTransaction::select(DB::raw(implode(',', $selectStatements)))
            ->addSelect('buyer_id as user_id')
            ->whereIn('buyer_id', $userIds)
            ->whereBetween('executed_date', array($startDate, $endDate))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');
    }

    public static function getSellerReferredFee($userIds, $startDate, $endDate)
    {
        $coins = MasterdataService::getCurrenciesAndCoins();

        $selectStatements = [];
        foreach ($coins as $coin) {
            $selectStatements[] = "SUM(IF(currency='{$coin}',sell_fee,0)) as {$coin}_fee";
        }
        return OrderTransaction::select(DB::raw(implode(',', $selectStatements)))
            ->addSelect('seller_id as user_id')
            ->whereIn('seller_id', $userIds)
            ->whereBetween('executed_date', array($startDate, $endDate))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');
    }

    public function getTotalFee($params)
    {
        $startDate = $params['start_date'];
        $endDate = $params['end_date'];
        return $this->buildGetFeeQuery($startDate, $endDate)->first();
    }

    public function getFee($params): Collection
    {
        $startDate = $params['start_date'];
        $endDate = $params['end_date'];
        $limit = $params['limit'];
        $sort = Arr::get($params, 'sort', 'executed_date');
        $sortType = Arr::get($params, 'sort_type', 'desc');
        $feesGroupByDate = $this->buildGetFeeQuery($startDate, $endDate)
            ->addSelect('executed_date')
            ->orderBy($sort, $sortType)
            ->groupBy('executed_date')
            ->paginate($limit);

        return collect([
            'total_fee' => $this->buildGetFeeQuery($startDate, $endDate)->first()
        ])->merge($feesGroupByDate);
    }

    private function buildGetFeeQuery($startDate, $endDate)
    {
        $coins = MasterdataService::getCurrenciesAndCoins();
        $selectStatements = [];
        $maxBotId = User::where('type', Consts::USER_TYPE_BOT)->max('id');
        if (!$maxBotId) {
            $maxBotId = 0;
        }
        foreach ($coins as $coin) {
            $selectStatements[] = "SUM(IF(coin='{$coin}' AND buyer_id>$maxBotId,buy_fee,IF(currency='{$coin}' AND seller_id>$maxBotId, sell_fee, 0))) as {$coin}_fee";
        }
        return OrderTransaction::select(DB::raw(implode(',', $selectStatements)))
            ->whereBetween('executed_date', array($startDate, $endDate));
    }

    public function orderByField($array, $field, $type)
    {
        $rs = $this->handleArrayBeforeSort($array, $field);
        $rs = json_decode(json_encode($rs), true);
        return $this->usortCustom($rs, $type);
    }

    public function usortCustom($array, $type)
    {
        if ($type == 'asc') {
            usort($array, function ($gt1, $gt2) {
                return BigNumber::new($gt1['fieldInOrderToSort']) < BigNumber::new($gt2['fieldInOrderToSort']);
            });
        } else {
            usort($array, function ($gt1, $gt2) {
                return BigNumber::new($gt1['fieldInOrderToSort']) > BigNumber::new($gt2['fieldInOrderToSort']);
            });
        }
        return $array;
    }

    public function getTradingsHistoryOrder($params, $orderId)
    {
        $transaction = OrderTransaction::when(!empty($orderId), function ($query) use ($params, $orderId) {
            return $query->select('*')
                ->selectRaw(
                    '(CASE WHEN transaction_type ="sell"
                        THEN sell_order_id
                        ELSE buy_order_id END)
                        AS order_Id'
                )
                ->selectRaw(
                    '(CASE WHEN transaction_type ="sell"
                        THEN seller_email
                        ELSE buyer_email END)
                        AS Email'
                )
                ->selectRaw(
                    '(CASE WHEN transaction_type ="sell"
                        THEN sell_fee
                        ELSE buy_fee END)
                        AS Fee'
                )
                ->where('buy_order_id', $orderId)
                ->orWhere('sell_order_id', $orderId);
        })
            ->when(array_key_exists('trade_type', $params), function ($query) use ($params, $orderId) {
                $query->where('transaction_type', $params['trade_type']);
                return $query;
            })
            ->when(array_key_exists('sort_type', $params) && (!empty($params['sort_type'])),
                function ($query) use ($params, $orderId) {
                    $type = $params['sort'];
                    switch ($type) {
                        case 'email':
                        case 'fee':
                        case 'order_id':
                            break;
                        case 'pair':
                            $query->orderBy('coin', $params["sort_type"])
                                ->orderBy('currency', $params["sort_type"]);
                            break;
                        default:
                            $query->orderBy($params["sort"], $params["sort_type"]);
                            break;
                    }
                    return $query;
                }, function ($query) use ($orderId) {
                    $query->orderBy('created_at', 'desc');
                })->get();

        if (array_key_exists('sort_type', $params) && !empty($params['sort_type'])) {
            $field = $params['sort'];
            $type = $params['sort_type'];
            if ($field == 'email' || $field == 'fee' || $field == 'order_id') {
                $transaction = $this->orderByField($transaction, $field, $type);
            }
        }
        return $transaction;
    }

    public function handleArrayBeforeSort($array, $field)
    {
        switch ($field) {
            case 'email':
                foreach ($array as $el) {
                    $type = $el['transaction_type'];
                    if ($type == 'buy') {
                        $value = $el['buyer_email'];
                    } else {
                        $value = $el['seller_email'];
                    }
                    $el['fieldInOrderToSort'] = $value;
                }
                break;
            case 'fee':
                foreach ($array as $el) {
                    $type = $el['transaction_type'];
                    if ($type == 'buy') {
                        $value = $el['buy_fee'];
                    } else {
                        $value = $el['sell_fee'];
                    }
                    $el['fieldInOrderToSort'] = $value;
                }
                break;
            case 'order_id':
                foreach ($array as $el) {
                    $type = $el['transaction_type'];
                    if ($type == 'buy') {
                        $value = $el['buy_order_id'];
                    } else {
                        $value = $el['sell_order_id'];
                    }
                    $el['fieldInOrderToSort'] = $value;
                }
                break;
        };
        return $array;
    }

    public function getTradingHistories($params)
    {
        $params = escapse_string_params($params);
        $query = null;
        if (!empty($params['trade_type'])) {
            $query = $this->buildGetTradingHistoryQuery($params['trade_type'], $params);
        } else {
            $buyOrders = $this->buildGetTradingHistoryQuery(Consts::ORDER_TRADE_TYPE_BUY, $params);
            $sellOrders = $this->buildGetTradingHistoryQuery(Consts::ORDER_TRADE_TYPE_SELL, $params);
            $query = $buyOrders->union($sellOrders);
        }

        $rawSql = $query->toSql();
        $bindings = $query->getBindings();
        return DB::table(DB::raw("($rawSql) AS orders"))
            ->when(!empty($params['sort']) && array_key_exists('sort_type', $params), function ($query) use ($params) {
                if ($params['sort'] == 'role') {
                    return $query->orderBy('role', $params['sort_type']);
                }
                return $query->orderBy($params['sort'], $params['sort_type']);
            }, function ($query) {
                return $query->orderBy('created_at', 'desc');
            })
            ->setBindings($bindings);
    }

    private function buildGetTradingHistoryQuery($tradeType, $params)
    {
        $fee = $this->getFeeColumnByTradeType($tradeType);
        $column = $this->getBuyerOrSeller($tradeType);
        return OrderTransaction::selectRaw("? as trade_type, {$fee} as fee, {$fee}_amal as fee_amal", [$tradeType])
            ->addSelect('order_transactions.created_at', 'order_transactions.currency', 'order_transactions.coin',
                'order_transactions.price', 'order_transactions.quantity', 'order_transactions.amount',
                'order_transactions.maker_email AS buyer_email', 'order_transactions.taker_email AS seller_email',
                'order_transactions.buy_order_id AS buy_order', 'order_transactions.sell_order_id AS sell_order')
            ->selectRaw("IF(? = 'buy', IF(order_transactions.buy_order_id > order_transactions.sell_order_id, 'TAKER', 'MAKER'), IF(order_transactions.sell_order_id > order_transactions.buy_order_id, 'TAKER', 'MAKER')) AS role",
                [strtolower($tradeType)])
            ->where($column, Auth::id())
            ->where("{$tradeType}_order.market_type", Consts::ORDER_MARKET_TYPE_TYPE_NORMAL)
            ->join("orders as buy_order", "order_transactions.buy_order_id", "=", "buy_order.id")
            ->join("orders as sell_order", "order_transactions.sell_order_id", "=", "sell_order.id")
            ->when(!empty($params['start_date']), function ($query) use ($params) {
                return $query->where('order_transactions.created_at', '>=', $params['start_date']);
            })
            ->when(!empty($params['end_date']), function ($query) use ($params) {
                return $query->where('order_transactions.created_at', '<=', $params['end_date']);
            })
            ->when(!empty($params['coin']), function ($query) use ($params) {
                return $query->where('order_transactions.coin', $params['coin']);
            })
            ->when(!empty($params['currency']), function ($query) use ($params) {
                return $query->where('order_transactions.currency', $params['currency']);
            });
    }

    private function getFeeColumnByTradeType($tradeType): string
    {
        if ($tradeType == Consts::ORDER_TRADE_TYPE_BUY) {
            return 'buy_fee';
        }
        return 'sell_fee';
    }

    private function getBuyerOrSeller($tradeType): ?string
    {
        if (Consts::ORDER_TRADE_TYPE_BUY === $tradeType) {
            return 'buyer_id';
        }
        if (Consts::ORDER_TRADE_TYPE_SELL === $tradeType) {
            return 'seller_id';
        }
        return null;
    }

    public function getTradingHistoriesForAdmin($params)
    {
        $orders = OrderTransaction::when(!empty($params['start_date']), function ($query) use ($params) {
            return $query->where('created_at', '>=', $params['start_date']);
        })
            ->when(!empty($params['end_date']), function ($query) use ($params) {
                return $query->where('created_at', '<=', $params['end_date']);
            })
            ->when(!empty($params['coin']), function ($query) use ($params) {
                return $query->where('coin', $params['coin']);
            })
            ->when(!empty($params['currency']), function ($query) use ($params) {
                return $query->where('currency', $params['currency']);
            })
            ->when(!empty($params['search_key']), function ($query) use ($params) {
                return $query->where('buyer_email', 'like', '%' . $params['search_key'] . '%')
                    ->orWhere('seller_email', 'like', '%' . $params['search_key'] . '%');
            })
            ->when(!empty($params['sort']) && array_key_exists('sort_type', $params), function ($query) use ($params) {
                return $query->orderBy($params['sort'], $params['sort_type']);
            }, function ($query) {
                return $query->orderBy('created_at', 'desc');
            });
        return $orders->paginate(Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE));
    }

    public function getOrderPending($params, $userId = null)
    {
        return $this->getOrderPendingWithoutPaginate($userId, $params)
            ->paginate(Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE));
    }

    public function getOrderPendingAll($params, $userId = null)
    {
        return $this->getOrderPendingWithoutPaginate($userId, $params)->get();
    }

    public function getOrderPendingWithoutPaginate($userId = null, $params = [])
    {
        $statuses = [
            Consts::ORDER_STATUS_NEW,
            Consts::ORDER_STATUS_PENDING,
            Consts::ORDER_STATUS_EXECUTING,
            Consts::ORDER_STATUS_STOPPING,
        ];

        return Order::query()->select('*')
            ->selectRaw('price * quantity  as total,(executed_quantity/quantity) AS sort_quantity')
            ->when(!empty($userId), function ($query) use ($params, $userId) {
                return $query->where('user_id', $userId);
            })
            ->when(!empty($params['start_date']), function ($query) use ($params) {
                return $query->where('created_at', '>=', $params['start_date']);
            })
            ->when(!empty($params['end_date']), function ($query) use ($params) {
                return $query->where('created_at', '<=', $params['end_date']);
            })
            ->when(!empty($params['coin']), function ($query) use ($params) {
                return $query->where('coin', $params['coin']);
            })
            ->when(!empty($params['currency']), function ($query) use ($params) {
                return $query->where('currency', $params['currency']);
            })
            ->when(!empty($params['trade_type']), function ($query) use ($params) {
                return $query->where('trade_type', $params['trade_type']);
            })
            ->when(isset($params['search_key']), function ($query) use ($params) {
                return $query->where('email', 'like', '%' . $params['search_key'] . '%');
            })
            ->when(isset($params['market_type']), function ($query) use ($params) {
                if ($params['market_type'] == Consts::ORDER_MARKET_TYPE_TYPE_CONVERT) {
                    return $query->where('market_type', Consts::ORDER_MARKET_TYPE_TYPE_CONVERT);
                }
            })
            ->when(!isset($params['market_type']), function ($query) use ($params) {
                return $query->where('market_type', Consts::ORDER_MARKET_TYPE_TYPE_NORMAL);
            })
            ->whereIn('status', $statuses)
            ->when(!empty($params['sort']), function ($query) use ($params) {
                $query->orderBy($params['sort'] == 'executed_quantity' ? 'sort_quantity' : $params["sort"],
                    $params["sort_type"]);
            }, function ($query) {
                $query->orderBy('created_at', 'desc');
            });
    }

    /**
     * @throws Exception
     */

    /**
     * @OA\get (
     *     path="/orders/:currency/:status",
     *     tags={"Trading"},
     *     summary="[Private] Current open orders (USER_DATA)",
     *     description="Query execution status of all open orders of user.",
     *     @OA\Parameter(
     *           name="currency",
     *           in="path",
     *           description="currency of order.",
     *           @OA\Schema(
     *               type="string",
     *               example="usd"
     *           )
     *       ),
     *     @OA\Parameter(
     *            name="status",
     *            in="path",
     *            description="Status of order.",
     *            @OA\Schema(
     *                type="string",
     *                example="new"
     *            )
     *     ),
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
     *                      @OA\Property(property="original_id", type="integer", nullable=true, example=null),
     *                      @OA\Property(property="user_id", type="integer", example=1),
     *                      @OA\Property(property="email", type="string", format="email", example="bot1@gmail.com"),
     *                      @OA\Property(property="trade_type", type="string", example="sell"),
     *                      @OA\Property(property="currency", type="string", example="usd"),
     *                      @OA\Property(property="coin", type="string", example="sol"),
     *                      @OA\Property(property="type", type="string", example="limit"),
     *                      @OA\Property(property="ioc", type="string", nullable=true, example=null),
     *                      @OA\Property(property="quantity", type="string", example="1.0000000000"),
     *                      @OA\Property(property="price", type="string", example="22.0000000000"),
     *                      @OA\Property(property="executed_quantity", type="string", example="0.0000000000"),
     *                      @OA\Property(property="executed_price", type="string", example="0.0000000000"),
     *                      @OA\Property(property="base_price", type="string", example="1.0000000000"),
     *                      @OA\Property(property="stop_condition", type="string", example="ge"),
     *                      @OA\Property(property="fee", type="string", example="0.0000000000"),
     *                      @OA\Property(property="status", type="string", example="new"),
     *                      @OA\Property(property="created_at", type="integer", example=1718013226632),
     *                      @OA\Property(property="updated_at", type="integer", example=1718013226632),
     *                      @OA\Property(property="market_type", type="integer", example=0)
     *                  )
     *              ),
     *              @OA\Property(property="first_page_url", type="string", example="http://localhost:8000/orders/usd/new?page=1"),
     *              @OA\Property(property="from", type="integer", example=1),
     *              @OA\Property(property="last_page", type="integer", example=3),
     *              @OA\Property(property="last_page_url", type="string", example="http://localhost:8000/orders/usd/new?page=3"),
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
     *              @OA\Property(property="next_page_url", type="string", example="http://localhost:8000/orders/usd/new?page=2"),
     *              @OA\Property(property="path", type="string", example="http://localhost:8000/orders/usd/new"),
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
     * ),
     * @OA\get (
     *     path="/orders/:currency/:status?start_date=&end_date=&limit=",
     *     tags={"Account"},
     *     summary="Account order history (USER_DATA)",
     *     description="Query information about all orders of user.",
     *     @OA\Parameter(
     *           name="currency",
     *           in="path",
     *           description="currency of order.",
     *           @OA\Schema(
     *               type="string",
     *               example="btc"
     *           )
     *       ),
     *     @OA\Parameter(
     *            name="status",
     *            in="path",
     *            description="Status of order.",
     *            @OA\Schema(
     *                type="string",
     *                example="canceled"
     *            )
     *     ),
     *     @OA\Parameter (
     *            name="start_date",
     *            in="query",
     *            description="Created at of order.",
     *            @OA\Schema(
     *                type="string",
     *                example="1712569761030"
     *            )
     *        ),
     *      @OA\Parameter (
     *             name="end_date",
     *             in="query",
     *             description="Created at of order.",
     *             @OA\Schema(
     *                 type="string",
     *                 example="1714635355028"
     *             )
     *      ),
     *      @OA\Parameter (
     *              name="limit",
     *              in="query",
     *              description="Limit per page.",
     *              @OA\Schema(
     *                  type="int",
     *                  example=6
     *              )
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
     *                      @OA\Property(property="id", type="integer", example=25),
     *                      @OA\Property(property="original_id", type="integer", nullable=true, example=null),
     *                      @OA\Property(property="user_id", type="integer", example=1),
     *                      @OA\Property(property="email", type="string", format="email", example="bot1@gmail.com"),
     *                      @OA\Property(property="trade_type", type="string", example="sell"),
     *                      @OA\Property(property="currency", type="string", example="btc"),
     *                      @OA\Property(property="coin", type="string", example="sol"),
     *                      @OA\Property(property="type", type="string", example="limit"),
     *                      @OA\Property(property="ioc", type="string", nullable=true, example=null),
     *                      @OA\Property(property="quantity", type="string", example="1.0000000000"),
     *                      @OA\Property(property="price", type="string", example="22.0000000000"),
     *                      @OA\Property(property="executed_quantity", type="string", example="0.0000000000"),
     *                      @OA\Property(property="executed_price", type="string", example="0.0000000000"),
     *                      @OA\Property(property="base_price", type="string", example="1.0000000000"),
     *                      @OA\Property(property="stop_condition", type="string", example="ge"),
     *                      @OA\Property(property="fee", type="string", example="0.0000000000"),
     *                      @OA\Property(property="status", type="string", example="new"),
     *                      @OA\Property(property="created_at", type="integer", example=1714631620115),
     *                      @OA\Property(property="updated_at", type="integer", example=1714631620115),
     *                      @OA\Property(property="market_type", type="integer", example=0)
     *                  )
     *              ),
     *              @OA\Property(property="first_page_url", type="string", example="http://localhost:8000/orders/btc/canceled?start_date=1712569761030&end_date=1714635355028?page=1"),
     *              @OA\Property(property="from", type="integer", example=1),
     *              @OA\Property(property="last_page", type="integer", example=3),
     *              @OA\Property(property="last_page_url", type="string", example="http://localhost:8000/orders/btc/canceled?start_date=1712569761030&end_date=1714635355028?page=3"),
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
     *              @OA\Property(property="next_page_url", type="string", example="http://localhost:8000/orders/btc/canceled?start_date=1712569761030&end_date=1714635355028?page=2"),
     *              @OA\Property(property="path", type="string", example="http://localhost:8000/orders/btc/canceled?start_date=1712569761030&end_date=1714635355028"),
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
     *    ),
     *
     *    @OA\get (
     *      path="/orders/:currency/:status?type=&start_date=&end_date=",
     *      tags={"Account"},
     *      summary="Account OCO history (USER_DATA)",
     *      description="Query information about OCO orders of user.",
     *      @OA\Parameter (
     *             name="type",
     *             in="query",
     *             description="type at of order.",
     *             @OA\Schema(
     *                 type="string",
     *                 example="oco"
     *             )
     *         ),
     *      @OA\Parameter (
     *             name="start_date",
     *             in="query",
     *             description="Created at of order.",
     *             @OA\Schema(
     *                 type="string",
     *                 example="1712569761030"
     *             )
     *         ),
     *       @OA\Parameter (
     *              name="end_date",
     *              in="query",
     *              description="Created at of order.",
     *              @OA\Schema(
     *                  type="string",
     *                  example="1714635355028"
     *              )
     *       ),
     *       @OA\Response(
     *       response="200",
     *       description="Successful response",
     *       @OA\JsonContent(
     *           type="object",
     *           @OA\Property(property="success", type="boolean", example=true),
     *           @OA\Property(property="message", type="string", nullable=true, example=null),
     *           @OA\Property(property="dataVersion", type="string", example="dc2daadb3085e1dfa7ee03bbdf2c2267acbcba57"),
     *           @OA\Property(
     *               property="data",
     *               type="object",
     *               @OA\Property(property="current_page", type="integer", example=1),
     *               @OA\Property(
     *                   property="data",
     *                   type="array",
     *                   @OA\Items(
     *                       type="object",
     *                       @OA\Property(property="id", type="integer", example=25),
     *                       @OA\Property(property="original_id", type="integer", nullable=true, example=null),
     *                       @OA\Property(property="user_id", type="integer", example=1),
     *                       @OA\Property(property="email", type="string", format="email", example="bot1@gmail.com"),
     *                       @OA\Property(property="trade_type", type="string", example="sell"),
     *                       @OA\Property(property="currency", type="string", example="btc"),
     *                       @OA\Property(property="coin", type="string", example="sol"),
     *                       @OA\Property(property="type", type="string", example="limit"),
     *                       @OA\Property(property="ioc", type="string", nullable=true, example=null),
     *                       @OA\Property(property="quantity", type="string", example="1.0000000000"),
     *                       @OA\Property(property="price", type="string", example="22.0000000000"),
     *                       @OA\Property(property="executed_quantity", type="string", example="0.0000000000"),
     *                       @OA\Property(property="executed_price", type="string", example="0.0000000000"),
     *                       @OA\Property(property="base_price", type="string", example="1.0000000000"),
     *                       @OA\Property(property="stop_condition", type="string", example="ge"),
     *                       @OA\Property(property="fee", type="string", example="0.0000000000"),
     *                       @OA\Property(property="status", type="string", example="new"),
     *                       @OA\Property(property="created_at", type="integer", example=1714631620115),
     *                       @OA\Property(property="updated_at", type="integer", example=1714631620115),
     *                       @OA\Property(property="market_type", type="integer", example=0)
     *                   )
     *               ),
     *               @OA\Property(property="first_page_url", type="string", example="http://localhost:8000/orders/btc/canceled?start_date=1712569761030&end_date=1714635355028?page=1"),
     *               @OA\Property(property="from", type="integer", example=1),
     *               @OA\Property(property="last_page", type="integer", example=3),
     *               @OA\Property(property="last_page_url", type="string", example="http://localhost:8000/orders/btc/canceled?start_date=1712569761030&end_date=1714635355028?page=3"),
     *               @OA\Property(
     *                   property="links",
     *                   type="array",
     *                   @OA\Items(
     *                       type="object",
     *                       @OA\Property(property="url", type="string", nullable=true, example=null),
     *                       @OA\Property(property="label", type="string", example="pagination.previous"),
     *                       @OA\Property(property="active", type="boolean", example=false)
     *                   )
     *               ),
     *               @OA\Property(property="next_page_url", type="string", example="http://localhost:8000/orders/btc/canceled?start_date=1712569761030&end_date=1714635355028?page=2"),
     *               @OA\Property(property="path", type="string", example="http://localhost:8000/orders/btc/canceled?start_date=1712569761030&end_date=1714635355028"),
     *               @OA\Property(property="per_page", type="integer", example=6),
     *               @OA\Property(property="prev_page_url", type="string", nullable=true, example=null),
     *               @OA\Property(property="to", type="integer", example=6),
     *               @OA\Property(property="total", type="integer", example=14)
     *           )
     *       )
     *      ),
     *      @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *               @OA\Property(property="success", type="boolean", example=false),
     *               @OA\Property(property="message", type="string", example="Server Error"),
     *               @OA\Property(property="dataVersion", type="string", example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"),
     *               @OA\Property(property="data", type="string", example=null)
     *           )
     *       ),
     *       @OA\Response(
     *           response=401,
     *           description="Unauthenticated",
     *           @OA\JsonContent(
     *               @OA\Property(property="message", type="string", example="Unauthenticated.")
     *           )
     *       ),
     *       security={{ "apiAuth": {} }}
     *  ),
     *
     *   @OA\get (
     *       path="/orders/:currency/:status?start_date=&end_date=",
     *       tags={"Account"},
     *       summary="Account trade history (USER_DATA)",
     *       description="Query information about all your trades, filtered by time range.",
     *       @OA\Parameter (
     *              name="start_date",
     *              in="query",
     *              description="Created at of order.",
     *              @OA\Schema(
     *                  type="string",
     *                  example="1712569761030"
     *              )
     *          ),
     *        @OA\Parameter (
     *               name="end_date",
     *               in="query",
     *               description="Created at of order.",
     *               @OA\Schema(
     *                   type="string",
     *                   example="1714635355028"
     *               )
     *        ),
     *        @OA\Response(
     *        response="200",
     *        description="Successful response",
     *        @OA\JsonContent(
     *            type="object",
     *            @OA\Property(property="success", type="boolean", example=true),
     *            @OA\Property(property="message", type="string", nullable=true, example=null),
     *            @OA\Property(property="dataVersion", type="string", example="dc2daadb3085e1dfa7ee03bbdf2c2267acbcba57"),
     *            @OA\Property(
     *                property="data",
     *                type="object",
     *                @OA\Property(property="current_page", type="integer", example=1),
     *                @OA\Property(
     *                    property="data",
     *                    type="array",
     *                    @OA\Items(
     *                        type="object",
     *                        @OA\Property(property="id", type="integer", example=25),
     *                        @OA\Property(property="original_id", type="integer", nullable=true, example=null),
     *                        @OA\Property(property="user_id", type="integer", example=1),
     *                        @OA\Property(property="email", type="string", format="email", example="bot1@gmail.com"),
     *                        @OA\Property(property="trade_type", type="string", example="sell"),
     *                        @OA\Property(property="currency", type="string", example="btc"),
     *                        @OA\Property(property="coin", type="string", example="sol"),
     *                        @OA\Property(property="type", type="string", example="limit"),
     *                        @OA\Property(property="ioc", type="string", nullable=true, example=null),
     *                        @OA\Property(property="quantity", type="string", example="1.0000000000"),
     *                        @OA\Property(property="price", type="string", example="22.0000000000"),
     *                        @OA\Property(property="executed_quantity", type="string", example="0.0000000000"),
     *                        @OA\Property(property="executed_price", type="string", example="0.0000000000"),
     *                        @OA\Property(property="base_price", type="string", example="1.0000000000"),
     *                        @OA\Property(property="stop_condition", type="string", example="ge"),
     *                        @OA\Property(property="fee", type="string", example="0.0000000000"),
     *                        @OA\Property(property="status", type="string", example="new"),
     *                        @OA\Property(property="created_at", type="integer", example=1714631620115),
     *                        @OA\Property(property="updated_at", type="integer", example=1714631620115),
     *                        @OA\Property(property="market_type", type="integer", example=0)
     *                    )
     *                ),
     *                @OA\Property(property="first_page_url", type="string", example="http://localhost:8000/orders/btc/canceled?start_date=1712569761030&end_date=1714635355028?page=1"),
     *                @OA\Property(property="from", type="integer", example=1),
     *                @OA\Property(property="last_page", type="integer", example=3),
     *                @OA\Property(property="last_page_url", type="string", example="http://localhost:8000/orders/btc/canceled?start_date=1712569761030&end_date=1714635355028?page=3"),
     *                @OA\Property(
     *                    property="links",
     *                    type="array",
     *                    @OA\Items(
     *                        type="object",
     *                        @OA\Property(property="url", type="string", nullable=true, example=null),
     *                        @OA\Property(property="label", type="string", example="pagination.previous"),
     *                        @OA\Property(property="active", type="boolean", example=false)
     *                    )
     *                ),
     *                @OA\Property(property="next_page_url", type="string", example="http://localhost:8000/orders/btc/canceled?start_date=1712569761030&end_date=1714635355028?page=2"),
     *                @OA\Property(property="path", type="string", example="http://localhost:8000/orders/btc/canceled?start_date=1712569761030&end_date=1714635355028"),
     *                @OA\Property(property="per_page", type="integer", example=6),
     *                @OA\Property(property="prev_page_url", type="string", nullable=true, example=null),
     *                @OA\Property(property="to", type="integer", example=6),
     *                @OA\Property(property="total", type="integer", example=14)
     *            )
     *        )
     *       ),
     *       @OA\Response(
     *          response=500,
     *          description="Server error",
     *          @OA\JsonContent(
     *                @OA\Property(property="success", type="boolean", example=false),
     *                @OA\Property(property="message", type="string", example="Server Error"),
     *                @OA\Property(property="dataVersion", type="string", example="6e7a7795297cdc4222ecb77463a7e83638d3f33f"),
     *                @OA\Property(property="data", type="string", example=null)
     *            )
     *        ),
     *        @OA\Response(
     *            response=401,
     *            description="Unauthenticated",
     *            @OA\JsonContent(
     *                @OA\Property(property="message", type="string", example="Unauthenticated.")
     *            )
     *        ),
     *        security={{ "apiAuth": {} }}
     *   )
     *
     */
    public function getOrder($user, $immediately, $currency, $status, $params)
    {
        if ($immediately) {
            DB::connection('master')->beginTransaction();
            DB::connection('master')->getPdo()->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
        } else {
            DB::beginTransaction();
            DB::connection()->getPdo()->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
        }
        try {
            $table = $immediately ? Order::on('master') : Order::on('mysql');
            $itemPerPage = $params['limit'] ?? 6;

            $start_date = $params['start_date'] ?? null;
            $end_date = $params['end_date'] ?? null;
            $type = $params['type'] ?? null;
            $_currency = $_status = null;
            if($currency != ':currency')  $_currency = $currency;
            if($status != ':status')  $_status = $status;

            switch ($status) {
                case Consts::ORDER_STATUS_PENDING:
                    $orders = $table->where('user_id', $user->id)
                        ->when($_currency, function ($query) use($_currency) {
                            $query->where('currency', $_currency);
                        })
                        ->whereIn('status', [Consts::ORDER_STATUS_PENDING, Consts::ORDER_STATUS_EXECUTING])
                        ->when($type, function ($query) use($type) {
                            $query->where('type', $type);
                        })
                        ->when($start_date && $end_date, function ($query) use($start_date, $end_date) {
                            $query->whereBetween('created_at', [$start_date, $end_date]);
                        })
                        ->orderBy('updated_at', 'desc')
                        ->paginate($itemPerPage)->appends($params);
                    break;
                case Consts::ORDER_STATUS_STOPPING:
                    $orders = $table->where('user_id', $user->id)
                        ->when($_currency, function ($query) use($_currency) {
                            $query->where('currency', $_currency);
                        })
                        ->when($_status, function ($query) use($_status) {
                            $query->where('status', $_status);
                        })
                        ->when($type, function ($query) use($type) {
                            $query->where('type', $type);
                        })
                        ->when($start_date && $end_date, function ($query) use($start_date, $end_date) {
                            $query->whereBetween('created_at', [$start_date, $end_date]);
                        })
                        ->orderBy('updated_at', 'desc')
                        ->paginate($itemPerPage)->appends($params);
                    break;
                case Consts::ORDER_STATUS_EXECUTED:
                    $executedQuery = 'orders.*';
                    $orders = $table->where('user_id', $user->id)
                        ->when($_currency, function ($query) use($_currency) {
                            $query->where('currency', $_currency);
                        })
                        ->when($_status, function ($query) use($_status) {
                            $query->where('status', $_status);
                        })
                        ->whereNull('original_id')
                        ->when($type, function ($query) use($type) {
                            $query->where('type', $type);
                        })
                        ->when($start_date && $end_date, function ($query) use($start_date, $end_date) {
                            $query->whereBetween('created_at', [$start_date, $end_date]);
                        })
                        ->selectRaw($executedQuery)
                        ->orderBy('updated_at', 'desc')
                        ->paginate($itemPerPage)->appends($params);
                    foreach ($orders as $order) {
                        if (!$order->executed_quantity) {
                            // if there is no sub order, order is executed 1 time => executed_quantity = quantity
                            $order->executed_quantity = $order->quantity;
                        }
                    }
                    break;
                default:
                    $orders = $table->where('user_id', $user->id)
                        ->when($_currency, function ($query) use($_currency) {
                            $query->where('currency', $_currency);
                        })
                        ->when($_status, function ($query) use($_status) {
                            $query->where('status', $_status);
                        })
                        ->when($type, function ($query) use($type) {
                            $query->where('type', $type);
                        })
                        ->when($start_date && $end_date, function ($query) use($start_date, $end_date) {
                            $query->whereBetween('created_at', [$start_date, $end_date]);
                        })
                        ->orderBy('updated_at', 'desc')
                        ->paginate($itemPerPage)->appends($params);
                    break;
            }
            if ($immediately) {
                DB::connection('master')->commit();
            } else {
                DB::commit();
            }
            return $orders;
        } catch (Exception $e) {
            if ($immediately) {
                DB::connection('master')->rollBack();
            } else {
                DB::rollBack();
            }

            throw $e;
        }
    }

    public function getMinTickerSize($currency, $coin)
    {
        $price_group = DB::connection('master')->table('price_groups')
            ->selectRaw('value')
            ->where('currency', $currency)
            ->where('coin', $coin)
            ->orderBy('value', 'asc')
            ->first();
        return $price_group->value;
    }

    public function getMarketFee($currency, $coin)
    {
        return MarketFeeSetting::select('fee_taker', 'fee_maker', 'currency', 'coin')
            ->where('coin', $coin)
            ->where('currency', $currency)
            ->first();
    }
}
