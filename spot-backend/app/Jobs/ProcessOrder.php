<?php

namespace App\Jobs;

use App\Consts;
use App\Utils;
use App\Models\Order;
use App\Http\Services\OrderService;
use App\Http\Services\PriceService;
use App\Utils\BigNumber;
use App\Utils\RedisLock;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Http\Services\HealthCheckService;
use App\Services\StreamMatchingEngine;

class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const ORDER_ACTION_ADD = 'add';
    const ORDER_ACTION_REMOVE = 'remove';

    // Dynamic polling constants
    private const MIN_SLEEP_US = 1000;      // 1ms minimum
    private const MAX_SLEEP_US = 50000;     // 50ms maximum
    private const EMPTY_THRESHOLD = 5;      // Consecutive empty count before backing off
    private const BATCH_SIZE = 20;          // Orders to match per batch

    private $orderService;
    private $priceService;

    protected $currency;
    protected $coin;

    protected $processingOrder;

    protected $redis;

    protected $lastRun;
    protected $lastMatchingSuccess;
    protected $checkingInterval;

    protected $healthCheck;
    protected $orderLoss;
    protected $countMatch;

    // Dynamic polling state
    protected $sleepTimeUs;
    protected $consecutiveEmpty;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($currency, $coin)
    {
        $this->currency = $currency;
        $this->coin = $coin;
        $this->orderService = new OrderService();
        $this->priceService = new PriceService();
        $this->lastMatchingSuccess = true;
        $this->checkingInterval = env('OP_CHECKING_INTERVAl', 3000);
        $this->orderLoss = [];
        $this->sleepTimeUs = self::MIN_SLEEP_US;
        $this->consecutiveEmpty = 0;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->redis = Redis::connection(static::getRedisConnection());

        $timeStart = microtime(true);
        if (Utils::isTesting()) {
            echo "\nStart: ".$timeStart;
        }

        $this->countMatch = 0;

        $this->clearCache();
        $this->loadOrders();

        // In testing mode, process all unprocessed orders first to add them to matching queue
        // This is needed because the while loop only runs once in testing mode
        if (Utils::isTesting()) {
            while ($this->processNextUnprocessedOrder()) {
                // Process all orders into matching queue
            }
            $this->lastMatchingSuccess = true;
        }

        $this->lastRun = Utils::currentMilliseconds();
        while (true) {
            if ($this->lastRun + $this->checkingInterval - 500 < Utils::currentMilliseconds()) {
                // if last matching take more than 3s to finish
                // we need end this processor, because other processor has been started
                return;
            }
            //echo "\nlanvo::handle run: ".$this->lastRun;

            $symbol = strtoupper($this->currency . $this->coin);
            $this->healthCheck = HealthCheckService::initForMatchingEngine(Consts::HEALTH_CHECK_DOMAIN_SPOT, $symbol);
            $this->healthCheck->matchingEngine();

            if (Utils::currentMilliseconds() - $this->lastRun > $this->checkingInterval / 2) {
                $this->lastRun = Utils::currentMilliseconds();
                $this->redis->set($this->getLastRunKey(), $this->lastRun);
                foreach ($this->orderLoss as $o) {
                    ProcessOrder::addToUnprocessedQueue($o, self::ORDER_ACTION_ADD);
                    //ProcessOrder::addOrder($o);
                }
            }
            // Batch matching: process multiple orders per iteration
            $batchMatchCount = 0;
            for ($i = 0; $i < self::BATCH_SIZE; $i++) {
                $matched = false;

                if ($this->lastMatchingSuccess) {
                    if ($this->matchMarketBuyOrder()) {
                        $matched = true;
                    } elseif ($this->matchMarketSellOrder()) {
                        $matched = true;
                    } elseif ($this->matchLimitOrders()) {
                        $matched = true;
                    }
                }

                if (!$matched && !$this->lastMatchingSuccess) {
                    if ($this->processNextUnprocessedOrder()) {
                        $matched = true;
                    }
                }

                if ($matched) {
                    $batchMatchCount++;
                    $this->lastMatchingSuccess = true;
                } else {
                    $this->lastMatchingSuccess = false;
                    break; // No more orders to match in this batch
                }
            }

            if (Utils::isTesting()) {
                // In testing, continue until no more matches
                if ($batchMatchCount == 0) {
                    break;
                }
                continue;
            }

            // Dynamic polling: adjust sleep time based on activity
            if ($batchMatchCount > 0) {
                // Orders matched - reset to minimum sleep
                $this->sleepTimeUs = self::MIN_SLEEP_US;
                $this->consecutiveEmpty = 0;
            } else {
                // No orders matched - exponential backoff
                $this->consecutiveEmpty++;
                if ($this->consecutiveEmpty > self::EMPTY_THRESHOLD) {
                    $this->sleepTimeUs = min($this->sleepTimeUs * 2, self::MAX_SLEEP_US);
                }
            }
            usleep($this->sleepTimeUs);
        }

        $timeEnd = microtime(true);
        if (Utils::isTesting()) {
            echo "\nSec:" . ($timeEnd - $timeStart);
            echo "\nCount match: " . $this->countMatch;
            echo "\nEnd: " . $timeEnd."\n";

        }

    }

    private function clearCache()
    {
        $this->redis->del(static::getUnprocessedQueueName($this->currency, $this->coin));
        $this->redis->del(static::getOrderSetByParams($this->currency, $this->coin, Consts::ORDER_TRADE_TYPE_BUY, Consts::ORDER_TYPE_LIMIT));
        $this->redis->del(static::getOrderSetByParams($this->currency, $this->coin, Consts::ORDER_TRADE_TYPE_BUY, Consts::ORDER_TYPE_MARKET));
        $this->redis->del(static::getOrderSetByParams($this->currency, $this->coin, Consts::ORDER_TRADE_TYPE_SELL, Consts::ORDER_TYPE_LIMIT));
        $this->redis->del(static::getOrderSetByParams($this->currency, $this->coin, Consts::ORDER_TRADE_TYPE_SELL, Consts::ORDER_TYPE_MARKET));
    }

	/**
	 * @throws Exception
	 */
	private function loadOrders()
    {
        $orders = $this->orderService->getMatchableOrders($this->currency, $this->coin);
        foreach ($orders as $order) {
            $this->addToUnprocessedQueue($order, self::ORDER_ACTION_ADD);
        }
    }

    private function getLastRunKey(): string
    {
        return 'last_run_' . $this->currency . '_' . $this->coin;
    }

	/**
	 * @throws Exception
	 */
	public static function onNewOrderCreated($order)
    {
        static::addToUnprocessedQueue($order, self::ORDER_ACTION_ADD);
    }

	/**
	 * @throws Exception
	 */
	public static function onOrderCanceled($order)
    {
        static::addToUnprocessedQueue($order, self::ORDER_ACTION_REMOVE);
    }

    static function addToUnprocessedQueue($order, $action)
    {
        // Use Redis Stream if enabled (Phase 3 optimization)
        if (env('USE_STREAM_MATCHING_ENGINE', false)) {
            static::addToStream($order, $action);
            return;
        }

        // Default: use Redis Sorted Set (Phase 1-2 implementation)
        $redis = Redis::connection(static::getRedisConnection());
        if ($action === self::ORDER_ACTION_ADD) {
            $time = $order->updated_at;
        } elseif ($action === self::ORDER_ACTION_REMOVE) {
            $time = Utils::currentMilliseconds();
        } else {
            throw new Exception("Invalid action $action");
        }
        $queueName = static::getUnprocessedQueueName($order->currency, $order->coin);
        static::slog("Add order({$order->id}), action $action to $queueName");
        $data = json_encode([
            'id' => $order->id,
            'action' => $action,
        ]);
        $redis->zadd($queueName, $time, $data);
    }

    /**
     * Add order to Redis Stream for stream-based matching engine.
     *
     * @param Order $order
     * @param string $action
     * @return void
     */
    static function addToStream($order, $action)
    {
        try {
            StreamMatchingEngine::publishOrder($order, $action);
            static::slog("Published order({$order->id}), action $action to stream");
        } catch (Exception $e) {
            Log::error("Failed to publish to stream: " . $e->getMessage());
            // Fallback to sorted set on stream failure
            static::addToSortedSet($order, $action);
        }
    }

    /**
     * Add order to Redis Sorted Set (legacy method).
     *
     * @param Order $order
     * @param string $action
     * @return void
     */
    private static function addToSortedSet($order, $action)
    {
        $redis = Redis::connection(static::getRedisConnection());
        $time = ($action === self::ORDER_ACTION_ADD) ? $order->updated_at : Utils::currentMilliseconds();
        $queueName = static::getUnprocessedQueueName($order->currency, $order->coin);
        $data = json_encode(['id' => $order->id, 'action' => $action]);
        $redis->zadd($queueName, $time, $data);
    }

    private function getNextUnprocessedOrder()
    {
        $queueName = $this->getUnprocessedQueueName($this->currency, $this->coin);

        // Use ZPOPMIN for atomic pop (Redis 5.0+)
        // This replaces zrange + zrem with a single atomic operation
        $result = $this->redis->zpopmin($queueName, 1);
        if (empty($result)) {
            return null;
        }

        // zpopmin returns [member => score], get the member (key)
        $member = array_key_first($result);
        $data = json_decode($member);

        // Fetch order without lock for better performance
        // Lock is acquired later in matchOrders() when needed
        $data->order = Order::on('master')->find($data->id);

        if ($data->action === self::ORDER_ACTION_ADD) {
            if ($data->order && $data->order->canMatching()) {
                return $data;
            } else {
                // Invalid order - log and return null (let next iteration handle)
                $orderId = $data->id;
                $status = $data->order ? $data->order->status : 'not_found';
                $this->log("Ignore order ({$orderId}, {$status})");
                return null;
            }
        } elseif ($data->action === self::ORDER_ACTION_REMOVE) {
            return $data;
        } else {
            throw new Exception("Invalid action {$data->action}");
        }
    }

	/**
	 * @throws Exception
	 */
	private function processNextUnprocessedOrder(): bool
	{
        $data = $this->getNextUnprocessedOrder();

        if (!$data) {
            return false;
        }
        $action = $data->action;
        $this->log("Process new order({$data->order->id}), action $action");

        if ($action === self::ORDER_ACTION_ADD) {
            $this->processingOrder = $data->order;
            static::addOrder($data->order);
        } elseif ($action === self::ORDER_ACTION_REMOVE) {
            $this->cancelOrder($data->order);
        } else {
            throw new Exception("Invalid action $action");
        }
        return true;
    }

    private static function getUnprocessedQueueName($currency, $coin): string
    {
        return 'un_processed_order_' . $currency . '_' . $coin;
    }

    public static function addOrder($order)
    {

        $redis = Redis::connection(static::getRedisConnection());

        if (!$order->canMatching()) {
            ProcessOrder::slog('ignore order ' . $order->id);
            return;
        }
        // if ($redis->get(ProcessOrder::getOrderKey($order))) {
        //     ProcessOrder::slog('already in cache' . $order->id);
        //     return;
        // }

        $set = ProcessOrder::getOrderSet($order);
        $score = ProcessOrder::getOrderScore($order);
        $data = ProcessOrder::getOrderIndexData($order);

        $priceKey = ProcessOrder::getOrderPriceKey($order);
        $price = BigNumber::new($order->price)->toString();
        $orderKey = ProcessOrder::getOrderKey($order);
        $iocKey = static::isMarketType($order->type) && $order->ioc ? static::getOrderIocKey($order) : "";
        if (ProcessOrder::addOrderToQueue($redis, $set, $score, $data, $priceKey, $price, $orderKey, $order->id, $iocKey)) {
            ProcessOrder::slog('Add order'.json_encode($order));
        } else {
            ProcessOrder::slog('Do not add order'.json_encode($order));
        }
    }

    static function addOrderToQueue($redis, $set, $score, $data, $priceKey, $price, $orderKey, $orderId, $iocKey)
    {
        $script = "
            local queueName = ARGV[1]
            local score = ARGV[2]
            local data = ARGV[3]
            local priceKey = ARGV[4]
            local price = ARGV[5]
            local orderKey = ARGV[6]
            local orderId = ARGV[7]
            local iocKey = ARGV[8]

            if redis.call('zcard', queueName) == 0 then
                redis.call('zadd', queueName, score, data)
                redis.call('set', priceKey, price)
                redis.call('set', orderKey, orderId)
                if iocKey ~= '' then
                    redis.call('set', iocKey, 1)
                end
                return 1
            end

            local lastData = redis.call('zrange', queueName, -1, -1)
            for index2, lastKey in pairs(lastData) do
                redis.call('zadd', queueName, score, data)
                redis.call('set', priceKey, price)
                redis.call('set', orderKey, orderId)
                if iocKey ~= '' then
                    redis.call('set', iocKey, 1)
                end
                return 1
            end

            return 0
        ";
        return $redis->eval($script, 0, $set, $score, $data, $priceKey, $price, $orderKey, $orderId, $iocKey);
    }

    private function getMatchedPair($buyOrderType, $sellOrderType)
    {
        $script = "
            local currency = ARGV[1]
            local coin = ARGV[2]
            local buyType = ARGV[3]
            local sellType = ARGV[4]
            local buySet = 'order.' .. currency .. '.' .. coin .. '.buy.' .. buyType
            local sellSet = 'order.' .. currency .. '.' .. coin .. '.sell.' .. sellType

            local buyKey, sellKey, buyOrderId, sellOrderId
            local iocKey, iocScoreKey

            -- get data from redis
            local buyData = redis.call('zrange', buySet, 0, 0)
            for index1, key in pairs(buyData) do
                buyKey = key
                buyOrderId = buyKey.sub(buyKey, buyKey:match('.*()_') + 1)
            end
            local sellData = redis.call('zrange', sellSet, 0, 0)
            for index, key in pairs(sellData) do
                sellKey = key
                sellOrderId = sellKey.sub(sellKey, sellKey:match('.*()_') + 1)
            end
            -- end get data from redis

            -- ioc order
            if buyType == 'market' and buyOrderId ~= nil then
                iocKey = 'order.' .. currency .. '.' .. coin .. '.' .. buyOrderId .. '.ioc'
                if redis.call('get', iocKey) then
                    if sellOrderId == nil then
                        sellOrderId = -1
                    else
                        iocScoreKey = 'order.' .. currency .. '.' .. coin .. '.' .. buyOrderId .. '.ioc_score'
                        if redis.call('exists', iocScoreKey) > 0 then
                            local iocScore = redis.call('get', iocScoreKey)
                            sellKey = nil
                            sellOrderId = -1
                            sellData = redis.call('zrangebyscore', sellSet, iocScore, iocScore, 'limit', 0, 1)
                            for index, key in pairs(sellData) do
                                sellKey = key
                                sellOrderId = sellKey.sub(sellKey, sellKey:match('.*()_') + 1)
                            end
                        end
                    end
                end
            end

            if sellType == 'market' and sellOrderId ~= nil then
                iocKey = 'order.' .. currency .. '.' .. coin .. '.' .. sellOrderId .. '.ioc'
                if redis.call('get', iocKey) then
                    if buyOrderId == nil then
                        buyOrderId = -1
                    else
                        iocScoreKey = 'order.' .. currency .. '.' .. coin .. '.' .. sellOrderId .. '.ioc_score'
                        if redis.call('exists', iocScoreKey) > 0 then
                            local iocScore = redis.call('get', iocScoreKey)
                            buyKey = nil
                            buyOrderId = -1
                            buyData = redis.call('zrangebyscore', buySet, iocScore, iocScore, 'limit', 0, 1)
                            for index, key in pairs(buyData) do
                                buyKey = key
                                buyOrderId = buyKey.sub(buyKey, buyKey:match('.*()_') + 1)
                            end
                        end
                    end
                end
            end
            -- end ioc order

            if buyOrderId == nil or sellOrderId == nil then
                return {}
            end

            local buyOrderKey = 'order.' .. currency .. '.' .. coin .. '.' .. buyOrderId
            local sellOrderKey = 'order.' .. currency .. '.' .. coin .. '.' .. sellOrderId
            local buyOrderPriceKey = 'order.' .. currency .. '.' .. coin .. '.' .. buyOrderId .. '.price'
            local sellOrderPriceKey = 'order.' .. currency .. '.' .. coin .. '.' .. sellOrderId .. '.price'

            if buyType == 'market' or sellType == 'market' then
                if buyKey ~= nil then redis.call('zrem', buySet, buyKey) end
                if sellKey ~= nil then redis.call('zrem', sellSet, sellKey) end
                redis.call('del', buyOrderPriceKey)
                redis.call('del', sellOrderPriceKey)
                redis.call('del', buyOrderKey)
                redis.call('del', sellOrderKey)
                if iocKey ~= nil then redis.call('del', iocKey) end
                if iocScoreKey ~= nil then redis.call('del', iocScoreKey) end
                return { buyOrderId, sellOrderId }
            end
            if tonumber(redis.call('get', buyOrderPriceKey)) >= tonumber(redis.call('get', sellOrderPriceKey)) then
                redis.call('zrem', buySet, buyKey)
                redis.call('zrem', sellSet, sellKey)
                redis.call('del', buyOrderPriceKey)
                redis.call('del', sellOrderPriceKey)
                redis.call('del', buyOrderKey)
                redis.call('del', sellOrderKey)
                return { buyOrderId, sellOrderId }
            end

        ";
	    return $this->redis->eval($script, 0, $this->currency, $this->coin, $buyOrderType, $sellOrderType);
    }

    private function matchMarketBuyOrder()
    {
        $pair = $this->getMatchedPair(Consts::ORDER_TYPE_MARKET, Consts::ORDER_TYPE_LIMIT);
        if (is_array($pair) && sizeof($pair) > 0) {
            $this->log("matchMarketBuyOrder: topMarketBuy: ".$pair[0].", sell: ".$pair[1]);
            $this->matchOrders($pair);
            return true;
        } else {
            $this->log("matchMarketBuyOrder: topMarketBuy: null, sell: null");
        }
    }

    private function matchMarketSellOrder()
    {
        $pair = $this->getMatchedPair(Consts::ORDER_TYPE_LIMIT, Consts::ORDER_TYPE_MARKET);
        if (is_array($pair) && sizeof($pair) > 0) {
            $this->log("matchMarketSellOrder: buy: ".$pair[0].", topMarketsell: ".$pair[1]);
            $this->matchOrders($pair);
            return true;
        } else {
            $this->log("matchMarketSellOrder: buy: null, topMarketsell: null");
        }
    }

    private function matchLimitOrders()
    {
        $pair = $this->getMatchedPair(Consts::ORDER_TYPE_LIMIT, Consts::ORDER_TYPE_LIMIT);
        if ($pair && is_array($pair) && sizeof($pair) > 0) {
            $this->log("matchLimitOrders: buy: ".$pair[0].", sell: ".$pair[1]);
            $this->matchOrders($pair);
            return true;
        } else {
            $this->log("matchLimitOrders: buy: null, sell: null");
        }
    }

    private function matchOrders($ids)
    {
        $this->healthCheck->matchingEngine();
        DB::connection('master')->beginTransaction();
        try {
            if ($this->cancelIocOrder($ids)) {
                DB::connection('master')->commit();
                return true;
            }
            //echo "\nlanvo::begin: ". json_encode($ids);

            $buyOrder = Order::on('master')->where('id', $ids[0])->lockForUpdate()->first();
            $sellOrder = Order::on('master')->where('id', $ids[1])->lockForUpdate()->first();

            if (!$buyOrder->canMatching() || !$sellOrder->canMatching()) {
                $this->cancelMatching($ids);
                DB::connection('master')->rollBack();
                return true;
            }
            $this->log('Matched orders: '.$buyOrder->id.' with '.$sellOrder->id);

            $isBuyerMaker = $this->processingOrder->id === $sellOrder->id;

            $fakeDataTradeSpot = env("FAKE_DATA_TRADE_SPOT", false);
            if ($fakeDataTradeSpot && isset(Consts::FAKE_CURRENCY_COINS[$buyOrder->coin.'_'.$buyOrder->currency])) {
                $keyPriceMatch = 'fakePriceOrderMatch' . $buyOrder->currency . $buyOrder->coin;
                $fakePriceOrderMatch = 0;
                if (Cache::has($keyPriceMatch)) {
                    $fakePriceOrderMatch = Cache::get($keyPriceMatch);
                }


                if (in_array($buyOrder->type, ["market", 'stop_market']) || in_array($sellOrder->type, ["market", 'stop_market'])) {
                    if ($fakePriceOrderMatch <= 0) {
                        return true;
                    }

                    if (in_array($buyOrder->type, ["market", 'stop_market']) ) {
                        $minPrice = BigNumber::new($fakePriceOrderMatch)->div(1.5)->sub(BigNumber::new($sellOrder->price))->toString();
                        $maxPriceSell = BigNumber::new($fakePriceOrderMatch)->mul(1.5)->sub(BigNumber::new($sellOrder->price))->toString();

                        if ($minPrice > $sellOrder->price) {
                            DB::connection('master')->commit();
                            ProcessOrder::addOrder($buyOrder);
                            $this->orderLoss[] = $sellOrder;

                            //echo "\nlanvo::canc buy: ". json_encode($ids);
                            return true;
                        } elseif ($maxPriceSell < 0 || $maxPriceSell > $sellOrder->price) {
                            DB::connection('master')->commit();

                            ProcessOrder::addOrder($sellOrder);
                            $this->orderLoss[] = $buyOrder;
//                            echo "\nlanvo::canc: ". json_encode($ids). ' '.json_encode([$fakePriceOrderMatch, $minPrice, $maxPriceSell, $sellOrder->price]);
                            return true;
                        }
                    } else {
                        $minPrice = BigNumber::new($fakePriceOrderMatch)->div(1.5)->sub(BigNumber::new($buyOrder->price))->toString();
                        $maxPriceBuy = BigNumber::new($fakePriceOrderMatch)->mul(1.5)->sub(BigNumber::new($buyOrder->price))->toString();
                        //dd($fakePriceOrderMatch, $minPrice, $maxPriceBuy, $buyOrder->price);

                        if ($minPrice > $buyOrder->price) {
                            DB::connection('master')->commit();

                            ProcessOrder::addOrder($buyOrder);
                            $this->orderLoss[] = $sellOrder;
                            //echo "\nlanvo::canc: ". json_encode($ids);
                            return true;
                        } elseif ($maxPriceBuy < 0 || $maxPriceBuy > $buyOrder->price) {
                            DB::connection('master')->commit();

                            ProcessOrder::addOrder($sellOrder);
                            $this->orderLoss[] = $buyOrder;
                            //echo "\nlanvo::canc: ". json_encode($ids). ' '.json_encode([$fakePriceOrderMatch, $minPrice, $maxPriceBuy, $buyOrder->price]);
                            return true;
                        }
                    }

                } else {
                    $minPriceBuy = BigNumber::new($fakePriceOrderMatch)->div(1.1)->sub(BigNumber::new($buyOrder->price))->toString();
                    $maxPriceBuy = BigNumber::new($fakePriceOrderMatch)->mul(1.5)->sub(BigNumber::new($buyOrder->price))->toString();
                    $minPriceSell = BigNumber::new($fakePriceOrderMatch)->div(1.5)->sub(BigNumber::new($sellOrder->price))->toString();


                    if ($minPriceBuy > $buyOrder->price) {
                        ProcessOrder::addOrder($sellOrder);
                        $this->orderLoss[] = $buyOrder;

                        DB::connection('master')->commit();

                        //echo "\nlanvo::canc buy/sell: ". json_encode($ids);
                        return true;
                    } else {
                        if (($maxPriceBuy < 0 || $maxPriceBuy > $buyOrder->price)) {
                            if ($minPriceSell > $sellOrder->price) {
                                ProcessOrder::addOrder($sellOrder);
                                $this->orderLoss[] = $buyOrder;

                                DB::connection('master')->commit();

                                //echo "\nlanvo::canc buy/sell3: ". json_encode($ids) . ' '.json_encode([$fakePriceOrderMatch, $minPriceBuy, $maxPriceBuy, $buyOrder->price, $minPriceSell, $sellOrder->price]);;
                                return true;
                            } else if ($minPriceSell >= 0) {
                                ProcessOrder::addOrder($buyOrder);
                                $this->orderLoss[] = $sellOrder;

                                DB::connection('master')->commit();

                                //echo "\nlanvo::canc buy/sell2: ". json_encode($ids). ' '.json_encode([$fakePriceOrderMatch, $minPriceBuy, $maxPriceBuy, $buyOrder->price, $minPriceSell, $sellOrder->price]);
                                return true;
                            }

                        }
                    }
                    if ($minPriceSell > $sellOrder->price) {
                        $isBuyerMaker = true;
                    }
                }

            }

            //dd($ids);
            $userAutoMatching = env('FAKE_USER_AUTO_MATCHING', 1);
            if ($userAutoMatching) {
                if ($sellOrder->user_id == $userAutoMatching) {
                    $isBuyerMaker = false;
                } else if ($buyOrder->user_id == $userAutoMatching) {
                    $isBuyerMaker = true;
                }
            }

            $remaining = $this->orderService->matchOrders($buyOrder, $sellOrder, $isBuyerMaker);

            DB::connection('master')->commit();
            if ($remaining) {
                // $this->log('Matched orders: '. $buyOrder->id.' with '.$sellOrder->id.' => '.$subOrder->id);
                // $this->setIocPriceIfNeed($buyOrder, $sellOrder, $subOrder);
                ProcessOrder::addOrder($remaining);
            }

            $this->countMatch++;
        } catch (Exception $e) {
            $this->rollBackAnLogError($e);
            $this->cancelMatching($ids);
        }
        // this should be executed after transaction is committed
        // $this->orderService->activeStopOrders($this->currency, $this->coin);
        return true;
    }

    private function cancelIocOrder($ids)
    {
        if ($ids[0] < 0) {
            $order = Order::on('master')->where('id', $ids[1])->first();
            $this->orderService->cancel($order->user_id, $order->id);
            return true;
        }
        if ($ids[1] < 0) {
            $order = Order::on('master')->where('id', $ids[0])->first();
            $this->orderService->cancel($order->user_id, $order->id);
            return true;
        }
    }

    private function setIocPriceIfNeed($buyOrder, $sellOrder, $subOrder)
    {
        if (ProcessOrder::isMarketType($buyOrder->type) && $buyOrder->ioc
                && $subOrder->trade_type == Consts::ORDER_TRADE_TYPE_BUY) {
            $iocScoreKey = ProcessOrder::getOrderIOCScoreKey($subOrder);
            $iocScore = static::getOrderScore($sellOrder);
            $this->redis->set($iocScoreKey, $iocScore);
        }
        if (ProcessOrder::isMarketType($sellOrder->type) && $sellOrder->ioc
                && $subOrder->trade_type == Consts::ORDER_TRADE_TYPE_SELL) {
            $iocScoreKey = ProcessOrder::getOrderIOCScoreKey($subOrder);
            $iocScore = static::getOrderScore($buyOrder);
            $this->redis->set($iocScoreKey, $iocScore);
        }
    }

    private function cancelMatching($ids)
    {
        $this->log("Matching error, readd orders to queue: " . json_encode($ids));
        // if cannot match orders (maybe one be canceled), re-add valid order to cache for next process
        $buyOrder = Order::on('master')->where('id', $ids[0])->first();
        if ($buyOrder && $buyOrder->canMatching()) {
            ProcessOrder::addOrder($buyOrder);
        } else {
            $this->log("Order not found: " . $ids[0]);
        }
        $sellOrder = Order::on('master')->where('id', $ids[1])->first();
        if ($sellOrder && $sellOrder->canMatching()) {
            ProcessOrder::addOrder($sellOrder);
        } else {
            $this->log("Order not found: " . $ids[1]);
        }
    }

    private function rollBackAnLogError(Exception $e)
    {
        DB::connection('master')->rollBack();
        Log::error($e);
        throw $e;
    }

    static function isLimitType($type)
    {
        return $type == Consts::ORDER_TYPE_LIMIT || $type == Consts::ORDER_TYPE_STOP_LIMIT;
    }

    static function isMarketType($type)
    {
        return $type == Consts::ORDER_TYPE_MARKET || $type == Consts::ORDER_TYPE_STOP_MARKET;
    }

    static function cancelOrder($order)
    {
        $redis = Redis::connection(static::getRedisConnection());
        $redis->pipeline(function ($redis) use ($order) {
            $redis->zrem(ProcessOrder::getOrderSet($order), ProcessOrder::getOrderIndexData($order));
            $redis->del(ProcessOrder::getOrderPriceKey($order));
            $redis->del(ProcessOrder::getOrderKey($order));
            $redis->del(ProcessOrder::getOrderIocKey($order));
            $redis->del(ProcessOrder::getOrderIocScoreKey($order));
        });
    }

    static function getOrderKey($order)
    {
        return ProcessOrder::getOrderKeyByParams($order->currency, $order->coin, $order->id);
    }

    static function getOrderKeyByParams($currency, $coin, $id)
    {
        return "order.{$currency}.{$coin}.{$id}";
    }

    static function getOrderPriceKey($order)
    {
        return ProcessOrder::getOrderPriceKeyByParams($order->currency, $order->coin, $order->id);
    }

    static function getOrderPriceKeyByParams($currency, $coin, $id)
    {
        return "order.{$currency}.{$coin}.{$id}.price";
    }

    static function getOrderIocKey($order)
    {
        return ProcessOrder::getOrderIocKeyByParams($order->currency, $order->coin, $order->id);
    }

    static function getOrderIocKeyByParams($currency, $coin, $id)
    {
        return "order.{$currency}.{$coin}.{$id}.ioc";
    }

    static function getOrderIocScoreKey($order)
    {
        return ProcessOrder::getOrderIocScoreKeyByParams($order->currency, $order->coin, $order->id);
    }

    static function getOrderIocScoreKeyByParams($currency, $coin, $id)
    {
        return "order.{$currency}.{$coin}.{$id}.ioc_score";
    }

    static function getOrderSet($order)
    {
        return ProcessOrder::getOrderSetByParams($order->currency, $order->coin, $order->trade_type, $order->type);
    }

    static function getOrderSetByParams($currency, $coin, $tradeType, $type)
    {
        if ($type == Consts::ORDER_TYPE_STOP_LIMIT) {
            $type = Consts::ORDER_TYPE_LIMIT;
        }
        if ($type == Consts::ORDER_TYPE_STOP_MARKET) {
            $type = Consts::ORDER_TYPE_MARKET;
        }
        return "order.{$currency}.{$coin}.{$tradeType}.{$type}";
    }

    private static function getOrderScore($order)
    {
        if (static::isLimitType($order->type)) {
            $factor = Consts::PRICE_FACTORS[$order->currency];
            $price = BigNumber::new($factor)->mul($order->price);
            if (Consts::ORDER_TRADE_TYPE_BUY == $order->trade_type) {
                return BigNumber::new('1000000000')->sub($price)->toString();
            } else {
                return BigNumber::new($price)->toString();
            }
        } elseif (static::isMarketType($order->type)) {
            return $order->updated_at;
        } else {
            throw new HttpException(422, __('exception.unknown_order_type', ['type' => json_encode($order)]));
        }
    }

    static function getOrderIndexData($order)
    {
        $data = "{$order->updated_at}_";
        $data .= $order->id;
        return $data;
    }

    private static function getRedisConnection()
    {
        return Consts::RC_ORDER_PROCESSOR;
    }

    private function log($message)
    {
        Log::info('==================== ' . $message);
    }

    static function slog($message)
    {
        Log::info('==================== ' . $message);
    }
}
