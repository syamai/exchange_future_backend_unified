<?php

namespace App\Jobs;

use App\Consts;
use App\Events\OrderBookUpdated;
use App\Events\OrderTransactionCreated;
use App\Events\PricesUpdated;
use App\Http\Services\HealthCheckService;
use App\Http\Services\MasterdataService;
use App\Http\Services\OrderService;
use App\Http\Services\PriceService;
use App\Models\CoinsConfirmation;
use App\Models\Order;
use App\Models\Price;
use App\Models\SpotCommands;
use App\Models\User;
use App\Utils;
use App\Utils\BigNumber;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SpotPlaceOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $orderService;
    private $priceService;

    protected $currency;
    protected $coin;
    protected $userId;
    protected $user;
    protected $typeRun;
    protected $runOrderStepMax;
    protected $lastRun;
    protected $checkingInterval;
    protected $redis;
    protected $balances;
    protected $currencyCoin;

    /**
     * Create a new job instance.
     *
     * @param $currency
     * @param $coin
     * @param $userId
     */
    public function __construct($type, $currency, $coin, $userId)
    {
        $this->runOrderStepMax = env('FAKE_PLACE_ORDER_STEP_MAX', 2);
        $this->typeRun = $type;
        $this->currency = $currency;
        $this->coin = $coin;
        $this->userId = $userId;
        $this->user = User::find($userId);
        $this->orderService = new OrderService();
        $this->priceService = new PriceService();
        $this->checkingInterval = env('OP_CHECKING_INTERVAl_FAKE', 30000);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->redis = Redis::connection(static::getRedisConnection());

        $this->lastRun = Utils::currentMilliseconds();
        $timeSleep = env('FAKE_PLACE_ORDER_TIME_SLEEP', 200000); // 200ms
        if ($this->typeRun == 'cancel') {
            $timeSleep = env('FAKE_PLACE_ORDER_CANCEL_TIME_SLEEP', $timeSleep);
        } elseif (in_array($this->typeRun, ['bid', 'ask'])) {
            $timeSleep = env('FAKE_PLACE_ORDER_BID_TIME_SLEEP', $timeSleep);
        }
        while (true) {
            if ($this->lastRun + $this->checkingInterval - 500 < Utils::currentMilliseconds()) {
                // if last matching take more than 3s to finish
                // we need end this processor, because other processor has been started
                return;
            }

            if (Utils::currentMilliseconds() - $this->lastRun > $this->checkingInterval / 2) {
                //echo "\nlanvo:set redis:".$this->getLastRunKey();
                $this->lastRun = Utils::currentMilliseconds();
                $this->redis->set($this->getLastRunKey(), $this->lastRun);
            }
            //echo "\nrun:".$this->currency."-". $this->coin;
            //$timeStart = microtime(true);

            $rs = $this->doJob($this->currency, $this->coin);
            if (!$rs) {
                return;
            }
            //$timeEnd = microtime(true);
            //$time = round($timeEnd - $timeStart, 3);
            //echo "\ntime:" . $time;
            if (Utils::isTesting()) {
                break;
            }
            usleep($timeSleep);
        }
    }

    private function truncate($value, $precision) {
        try {
            if ($value == 0) {
                return 0;
            }

            if ($precision > 16) {
                $precision = 16;
            }

            if ($precision == 0) {
                return floor($value);
            }

            $scale = pow(10, $precision);

            return floor($value * $scale) / $scale;
        } catch (Exception $e) {
            return $value;
        }
    }

    private function getDecimalPlaces($value) {
        if (str_contains((string)$value, '.')) {
            $decimalPart = rtrim(substr(strrchr((string)$value, '.'), 1), '0');
            return strlen($decimalPart);
        }
        return 0;
    }

    private function randomFrom($min, $max, $decimals) {
        if ($max < $min) {
            throw new Exception("Max value must be greater than or equal to min value.");
        }

        $power = pow(10, $decimals);
        $range = ($max - $min) * $power;

        $randomNumber = mt_rand(0, (int)$range);

        return BigNumber::round(BigNumber::new($min + $randomNumber / $power), BigNumber::ROUND_MODE_HALF_UP, 10);
    }

    private function getLastRunKey(): string
    {
        return 'last_run_place_order_'. $this->typeRun . '_' . $this->currency . '_' . $this->coin;
    }

    private function getLastPrice($currency, $coin) {
        $exchangeLastPrice = 0;
        try {
            $baseUri = env("DOMAIN_MATCHING_JAVA_API_URL", "");
            if (!$baseUri) {
                throw new Exception('getOrderBook:ME:URL');
            }
            $client = new Client([
                'base_uri' => $baseUri
            ]);
            $url = 'api/spot/lastPrice/' . strtoupper($coin.$currency);
            $response = $client->get($url, [
                'timeout' => 5,
                'connect_timeout' => 5,
            ]);

            $responseObj = collect(json_decode($response->getBody()->getContents()));
            if (!$responseObj->isEmpty()) {
                $exchangeLastPrice = $responseObj->get('lastPrice');
            }

            if ($exchangeLastPrice <= 0) {
                $price = $this->priceService->getPrice($currency, $coin, true);
                if (!$price) {
                    throw new Exception('getLastPriceME:error:service:getprice');
                }
                $exchangeLastPrice = $price->price;
            }
        } catch (Exception $e) {
            Log::error("GetLastPrice:MELastPrice:" . $e->getMessage());
            throw new Exception('getLastPriceME:error');
        }
        return $exchangeLastPrice;
    }

    protected function doJob($currency = 'usdt', $coin = 'sol')
    {
        try {
            //$maxPrice = 1000;
            //$minPrice = 10;
            $minQuoteQty = 10;
            $maxQuoteQty = 1000;

            /*$price = $this->priceService->getPrice($currency, $coin, true);

            if (!$price) {
                return false;
            }
            $exchangeLastPrice = $price->price;
            */

            $exchangeLastPrice = $this->getLastPrice($currency, $coin);
            //$minQuoteQty = BigNumber::round(BigNumber::new($minPrice)->div($exchangeLastPrice)->toString(), BigNumber::ROUND_MODE_HALF_UP, 10);
            //$maxQuoteQty = BigNumber::round(BigNumber::new($maxPrice)->div($exchangeLastPrice)->toString(), BigNumber::ROUND_MODE_HALF_UP, 10);



            $refLastPrice = $this->getPriceRefExchange($coin, $currency);
            $currencyCoins = MasterdataService::getOneTable('coin_settings');
            $currencyCoin = $currencyCoins->filter(function ($item) use ($currency, $coin) {
                return $item->coin == $coin && $item->currency == $currency;
            })->first();
            /*

            $currencyCoin->quotePrecision = MasterdataService::getOneTable('coins')
                ->filter(function ($value, $key) use ($currency) {
                    return $value->coin == $currency;
                })
                ->pluck('decimal')->first();
            $currencyCoin->basePrecision = MasterdataService::getOneTable('coins')
                ->filter(function ($value, $key) use ($coin) {
                    return $value->coin == $coin;
                })
                ->pluck('decimal')->first();
            */
            $currencyCoin->quotePrecision = $this->getDecimalPlaces($currencyCoin->price_precision);
            $currencyCoin->basePrecision = $this->getDecimalPlaces($currencyCoin->quantity_precision);

            $tickerSize = MasterdataService::getOneTable('price_groups')
                ->filter(function ($value, $key) use ($currency, $coin) {
                    return $value->currency == $currency && $value->coin == $coin;
                })
                ->pluck('value')
                ->first();
            //$orderBook = $this->orderService->getOrderBook($currency, $coin, $tickerSize, false, true);
            $orderBook = $this->orderService->getOrderBookMatchingEngine($currency, $coin, $tickerSize, $refLastPrice);

            $currencies = CoinsConfirmation::query()
                ->select('coin')
                ->whereIn('coin', [$currency, $coin])
                ->pluck('coin');

            $balances = collect([]);
            foreach ($currencies as $cur) {
                $currencyTable = 'spot_' . $cur . '_accounts';
                $balance = DB::connection('master')
                    ->table($currencyTable)
                    ->select(['id', 'balance', 'available_balance'])
                    ->where('id', $this->userId)
                    ->first();
                if ($balance) {
                    $balances->add((object)[
                        'symbol' => $cur,
                        'amount' => $balance->available_balance
                    ]);
                }
            }

            if (!$currencyCoin || $refLastPrice <= 0 || !$balances) {
                return false;
            }


            $baseBalance = $balances->filter(function ($item) use ($coin) {
                return $item->symbol == $coin;
            })->first();

            if (!$baseBalance || $baseBalance->amount <= 0) {
                return false;
            }
            $quoteBalance = $balances->filter(function ($item) use ($currency) {
                return $item->symbol == $currency;
            })->first();

            if (!$quoteBalance || $quoteBalance->amount <= 0) {
                return false;
            }
            $this->balances = $balances;
            $this->currencyCoin = $currencyCoin;

            $refLastPrice = $this->truncate($refLastPrice, $currencyCoin->quotePrecision);

            match($this->typeRun) {
                'price' => $this->lastPriceTask($currencyCoin, $refLastPrice, $minQuoteQty, $maxQuoteQty, $exchangeLastPrice, $orderBook, $coin, $currency),
                'bid' => $this->bidTask($currencyCoin, $refLastPrice, $minQuoteQty, $maxQuoteQty, $exchangeLastPrice, $orderBook, $coin, $currency),
                'ask' => $this->askTask($currencyCoin, $refLastPrice, $minQuoteQty, $maxQuoteQty, $exchangeLastPrice, $orderBook, $coin, $currency),
                'cancel' => $this->cancelTask($currencyCoin, $refLastPrice, $minQuoteQty, $maxQuoteQty, $exchangeLastPrice, $orderBook, $coin, $currency),
                'test' => $this->testTask($currencyCoin, $refLastPrice, $minQuoteQty, $maxQuoteQty, $exchangeLastPrice, $orderBook, $coin, $currency),
            };


        } catch (Exception $ex) {
            //dd($ex);
            Log::error("SpotFakeData:fake:error");
            Log::error($ex);
            return false;
        }
        return true;
    }

    private function cancelOrder($orderId) {
        //$this->orderService->cancel($this->userId, $orderId);
        $order = Order::find($orderId);
        if ($order->user_id != $this->userId) {
            return;
        }

        if ($order->canCancel()) {
            try {
                //DB::connection('master')->transaction(function () use (&$order) {
                    $matchingJavaAllow = env("MATCHING_JAVA_ALLOW", false);
                    try {
                        if ($matchingJavaAllow) {
                            //check order reject
                            $typeName = "cancel";
                            //send kafka ME
                            $dataOrder = [
                                'type' => $typeName,
                                'data' => [
                                    'orderId' => $order->id,
                                    'userId' => $order->user_id,
                                    'currency' => $order->currency,
                                    'coin' => $order->coin,
                                ]
                            ];

                            $commandKey = md5(json_encode($dataOrder));
                            $command = SpotCommands::on('master')->where('command_key', $commandKey)->first();
                            if ($command && $command->status != 'pending') {
                                $command->delete();
                                $command = null;
                            }
                            if(!$command) {
                                $command = SpotCommands::create([
                                    'command_key' => $commandKey,
                                    'type_name' => $typeName,
                                    'user_id' => $order->user_id,
                                    'obj_id' => $order->id,
                                    'payload' => json_encode($dataOrder)

                                ]);
                                if (!$command) {
                                    throw new Exception('can not create command');
                                }
                                $dataOrder['data']['commandId'] = $command->id;
                                Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_COMMAND, $dataOrder);
                            }
                        } else {
                            $this->orderService->cancelOrder($order);
                        }
                    } catch (Exception $ex) {
                        Log::error("++++++++++++++++++++ Cancel Order Bot: {$order->id}");
                        Log::error($ex);
                    }
                //}, 3);
            } catch (\Exception $e) {
                Log::error($e);
                throw $e;
            }
        }
    }

    private function getOrderStatus($order): string
    {
        if ($order->type === Consts::ORDER_TYPE_LIMIT || $order->type === Consts::ORDER_TYPE_MARKET) {
            return Consts::ORDER_STATUS_PENDING;
        } else {
            $currentPrice = $this->priceService->getPrice($order->currency, $order->coin)->price;
            $basePrice = $order->base_price;
            $stopCondition = $order->stop_condition;
            if (($stopCondition == Consts::ORDER_STOP_CONDITION_GE && BigNumber::new($currentPrice)->comp($basePrice) >= 0)
                || ($stopCondition == Consts::ORDER_STOP_CONDITION_LE && BigNumber::new($currentPrice)->comp($basePrice) <= 0)) {
                return Consts::ORDER_STATUS_PENDING;
            } else {
                return Consts::ORDER_STATUS_STOPPING;
            }
        }
        throw new \Exception('Cannot determine order status');
    }

    private function placeLimitOrder($coin, $currency, $type, $qty, $lastPrice) {
//        $this->orderService->createOrder($request);
        if (!$this->user || !$this->currencyCoin) {
            return;
        }

        if ($type == Consts::ORDER_TRADE_TYPE_BUY) {

            $quoteBalance = $this->balances->filter(function ($item) use ($currency) {
                return $item->symbol == $currency;
            })->first();

            if (!$quoteBalance || BigNumber::new($quoteBalance->amount)->sub($qty * $lastPrice)->toString() < 0) {
                return false;
            }
        } else {
            $baseBalance = $this->balances->filter(function ($item) use ($coin) {
                return $item->symbol == $coin;
            })->first();
            if (!$baseBalance || BigNumber::new($baseBalance->amount)->sub($qty)->toString() < 0) {
                return false;
            }
        }

        $inputs = [
            'coin' => $coin,
            'currency' => $currency,
            'price' => $lastPrice,
            'quantity' => $qty,
            'trade_type' => $type,
            'type' => 'limit',
            'user_id' => $this->user->id,
            'email' => $this->user->email,
        ];

        $inputs['status'] = Consts::ORDER_STATUS_NEW;
        if (array_key_exists('price', $inputs)) {
            $inputs['reverse_price'] = BigNumber::new($inputs['price'])->mul(-1)->toString();
        }

        $inputs['fee'] = 0;
        $inputs['created_at'] = Utils::currentMilliseconds();
        $inputs['updated_at'] = Utils::currentMilliseconds();
        $order = null;
        DB::connection('master')->transaction(function () use ($inputs, &$order) {
            $order = Order::on('master')->create($inputs);
        }, 3);
        if($order) {
            //ProcessOrderRequest::dispatch($order->id, ProcessOrderRequest::CREATE);
            try {
                DB::connection('master')->transaction(function () use (&$order) {
                    if ($order->status !== Consts::ORDER_STATUS_NEW) {
                        logger("Invalid order status ({$order->id}, {$order->status})");
                        return;
                    }
                    $order->status = $this->getOrderStatus($order);
                    if ($this->orderService->updateBalanceForNewOrder($order)) {
                        $this->orderService->sendUpdateOrderBookEvent(Consts::ORDER_BOOK_UPDATE_CREATED, [$order]);
                    } else {
                        $order->status = Consts::ORDER_STATUS_CANCELED;
                    }
                    $order->save();
                }, 3);

                if ($order && $order->canMatching()) {
                    $matchingJavaAllow = env("MATCHING_JAVA_ALLOW", false);
                    try {
                        if ($matchingJavaAllow) {
                            //send kafka ME
                            $pricePrecision = $this->currencyCoin->price_precision;
                            $quantityPrecision = $this->currencyCoin->quantity_precision;
                            $dataOrder = [
                                'type' => "order",
                                'data' => [
                                    'orderId' => $order->id,
                                    'userId' => $order->user_id,
                                    'currency' => $order->currency,
                                    'coin' => $order->coin,
                                    'tradeType' => $order->trade_type,
                                    'type' => $order->type,
                                    'price' => BigNumber::round(BigNumber::new($order->price)->div($pricePrecision), BigNumber::ROUND_MODE_HALF_UP, 0),
                                    'quantity' => BigNumber::round(BigNumber::new($order->quantity)->sub(BigNumber::new($order->executed_quantity))->div($quantityPrecision), BigNumber::ROUND_MODE_HALF_UP, 0),
                                ]
                            ];

                            $command = SpotCommands::create([
                                'command_key' => md5(json_encode($dataOrder)),
                                'type_name' => 'order',
                                'user_id' => $order->user_id,
                                'obj_id' => $order->id,
                                'payload' => json_encode($dataOrder)

                            ]);
                            if (!$command) {
                                throw new Exception('can not create command');
                            }
                            $dataOrder['data']['commandId'] = $command->id;

                            Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_COMMAND, $dataOrder);
                        }
                    } catch (Exception $ex) {
                        Log::error("++++++++++++++++++++ Place Order Bot: {$order->id}");
                        Log::error($ex);
                    }
                }
            } catch (\Exception $e) {
                Log::error($e);
                throw $e;
            }
        }
    }

    private static function getRedisConnection()
    {
        return Consts::RC_ORDER_PROCESSOR;
    }

    private function getPriceRefExchange($coin, $currency)
    {
        try {
            $coin = strtoupper($coin);
            $currency = strtoupper($currency);
            $symbol = $coin.$currency;
            $now = Utils::currentMilliseconds();

            $key = "getPriceRefExchange:$currency:$coin";
            if (Cache::has($key)) {
                $result = Cache::get($key);
                if ($result->LastUpdate + 1000 >= $now) {
                    return $result->price;
                }
            }

            $price = 0;
            // get price binance
            try {
                $client = new Client([
                    'base_uri' => Consts::DOMAIN_BINANCE_API
                ]);

                $response = $client->get('api/v3/ticker/price', [
                    'query' => [
                        'symbol' => $symbol,
                    ],
                    'timeout' => 5,
                    'connect_timeout' => 5,
                ]);

                $responseObj = collect(json_decode($response->getBody()->getContents()));
                if (!$responseObj->isEmpty()) {
                    $price = $responseObj->get('price');
                }

            } catch (Exception $e) {
                Log::error("RefExchange:BinanceLastPrice:" . $e->getMessage());
            }

            // get Coinbase
            if ($price <= 0) {
                try {
                    $client = new Client([
                        'base_uri' => Consts::DOMAIN_COINBASE_API
                    ]);

                    $response = $client->get('v2/exchange-rates', [
                        'query' => [
                            'currency' => $coin,
                        ],
                        'timeout' => 5,
                        'connect_timeout' => 5,
                    ]);

                    $responseObj = collect(json_decode($response->getBody()->getContents()));
                    if (!$responseObj->isEmpty()) {
                        $price = $responseObj->get('data')->rates->{$currency};

                    }


                } catch (Exception $e) {
                    Log::error("RefExchange:CoinbaseLastPrice:" . $e->getMessage());
                }
            }

            if ($price > 0) {
                $result = (object) [
                    'price' => $price,
                    'LastUpdate' => $now
                ];

                Cache::forever($key, $result);
                return $price;
            }


        } catch (Exception $ex) {
            Log::error("RefExchange:GetPrice" . $ex->getMessage());
        }
        return 0;
    }

    private function lastPriceTask($currencyCoin, $refLastPrice, $minQuoteQty, $maxQuoteQty, $exchangeLastPrice, $orderBook, $coin, $currency)
    {
        $range = 4 / pow(10, $currencyCoin->quotePrecision);
        $lastPrice = $this->randomFrom($refLastPrice - $range, $refLastPrice + $range, $currencyCoin->quotePrecision);
        if ($lastPrice > 0) {
            $quoteQty1 = $this->randomFrom($minQuoteQty, ($minQuoteQty + $maxQuoteQty) / 2, $currencyCoin->quotePrecision);
            $qty1 = $this->truncate($quoteQty1 / $lastPrice, $currencyCoin->basePrecision);
            if ($qty1 > 0) {

                if ($exchangeLastPrice < $lastPrice) {
                    $quoteQty = 0;
                    if ($orderBook['sell']) {
                        $quoteQty = $orderBook['sell']->filter(function ($item) use ($lastPrice) {
                            return $item->price <= $lastPrice;
                        })->sum(fn($item) => $item->price * $item->quantity);
                    }
                    $qty = $this->truncate($quoteQty / $lastPrice, $currencyCoin->basePrecision);
                    if ($quoteQty <= $minQuoteQty /*|| $quoteQty >= $maxQuoteQty*/) {
                        $quoteQty = $this->randomFrom($minQuoteQty, $maxQuoteQty, $currencyCoin->quotePrecision);
                        $qty = $this->truncate($quoteQty / $lastPrice, $currencyCoin->basePrecision);
                    }

                    //Place Order
                    $this->placeLimitOrder($coin, $currency, Consts::ORDER_TRADE_TYPE_BUY, $qty, $lastPrice);
                    $this->placeLimitOrder($coin, $currency, Consts::ORDER_TRADE_TYPE_SELL, $qty1, $lastPrice);
                } else {
                    $quoteQty = 0;
                    if ($orderBook['buy']) {
                        $quoteQty = $orderBook['buy']->filter(function ($item) use ($lastPrice) {
                            return $item->price >= $lastPrice;
                        })->sum(fn($item) => $item->price * $item->quantity);
                    }

                    $qty = $this->truncate($quoteQty / $lastPrice, $currencyCoin->basePrecision);
                    if ($quoteQty <= $minQuoteQty /*|| $quoteQty >= $maxQuoteQty*/) {
                        $quoteQty = $this->randomFrom($minQuoteQty, $maxQuoteQty, $currencyCoin->quotePrecision);
                        $qty = $this->truncate($quoteQty / $lastPrice, $currencyCoin->basePrecision);
                    }

                    //Place Order
                    $this->placeLimitOrder($coin, $currency, Consts::ORDER_TRADE_TYPE_SELL, $qty, $lastPrice);
                    $this->placeLimitOrder($coin, $currency, Consts::ORDER_TRADE_TYPE_BUY, $qty1, $lastPrice);
                }
            }
        }
        return true;
    }

    private function getOpenOrder($type = null) {
        return DB::table('orders')
            ->where('user_id', $this->userId)
            ->where('currency', $this->currency)
            ->where('coin', $this->coin)
            ->whereIn('status', [Consts::ORDER_STATUS_PENDING, Consts::ORDER_STATUS_EXECUTING])
            ->whereIn('type', [Consts::ORDER_TYPE_LIMIT, Consts::ORDER_TYPE_STOP_LIMIT])
            ->when($type, function ($query) use ($type) {
                $query->where('trade_type', $type);
            })
            ->select(['id', 'quantity', 'executed_quantity', 'type', 'stop_condition', 'trade_type', 'base_price', 'price', 'currency', 'coin', 'updated_at'])
            ->get();
    }

    private function getCancelOrder($min, $max, $limit = 100) {
        return DB::table('orders')
            ->where('user_id', $this->userId)
            ->where('currency', $this->currency)
            ->where('coin', $this->coin)
            ->whereIn('status', [Consts::ORDER_STATUS_PENDING, Consts::ORDER_STATUS_EXECUTING])
            ->whereIn('type', [Consts::ORDER_TYPE_LIMIT, Consts::ORDER_TYPE_STOP_LIMIT])
            ->where(function ($query) use ($max, $min){
                $query->orWhere('price', '>', $max);
                $query->orWhere('price', '<', $min);
            })
            ->limit($limit)
            ->select(['id', 'quantity', 'executed_quantity', 'type', 'stop_condition', 'trade_type', 'base_price', 'price', 'currency', 'coin', 'updated_at'])
            ->get();
    }

    private function getCancelOrderAmount($amount, $limit = 100) {
        $timeCancel = Carbon::now()->subHour(5)->timestamp * 1000;
        $timeAmountCancel = Carbon::now()->subMinutes(5)->timestamp * 1000;
        return DB::table('orders')
            ->where('user_id', $this->userId)
            ->where('currency', $this->currency)
            ->where('coin', $this->coin)
            ->whereIn('status', [Consts::ORDER_STATUS_PENDING, Consts::ORDER_STATUS_EXECUTING])
            ->whereIn('type', [Consts::ORDER_TYPE_LIMIT, Consts::ORDER_TYPE_STOP_LIMIT])
            ->whereRaw("(((quantity - executed_quantity) * price >= {$amount} and created_at < {$timeAmountCancel}) or created_at < {$timeCancel})")
            ->select(['id', 'quantity', 'executed_quantity', 'type', 'stop_condition', 'trade_type', 'base_price', 'price', 'currency', 'coin', 'updated_at'])
            ->limit($limit)
            ->orderByDesc('id')
            ->get();
    }

    private function bidTask($currencyCoin, $refLastPrice, $minQuoteQty, $maxQuoteQty, $exchangeLastPrice, $orderBook, $coin, $currency)
    {
        $openOrders = $this->getOpenOrder(Consts::ORDER_TRADE_TYPE_BUY);
        $bidPercents = MasterdataService::getOneTable('bot_settings')
            ->filter(function ($value, $key) use ($currency, $coin) {
                return $value->currency == $currency && $value->coin == $coin;
            })
            ->pluck('bids')
            ->first();
        if ($bidPercents) {
            $bidPercents = json_decode($bidPercents, true);
        }
        if (!$bidPercents) {
            return false;
        }

        $limitOrder = env('FAKE_PLACE_ORDER_LIMIT', 2);
        for ($i = 0; $i < count($bidPercents); $i++) {
            $bidPercent = $bidPercents[$i][0];
            $targetVol = $bidPercents[$i][1];

            $toPrice = $i == 0 ? $refLastPrice : $refLastPrice - $refLastPrice * $bidPercents[$i - 1][0] / 100;
            $toPrice = $this->truncate($toPrice, $currencyCoin->quotePrecision);

            $fromPrice = $refLastPrice - $refLastPrice * $bidPercent/100;
            $fromPrice = $this->truncate($fromPrice, $currencyCoin->quotePrecision);

            $currentVol = 0;
            if ($orderBook['buy']) {
                $currentVol = $orderBook['buy']->filter(function ($item) use ($fromPrice, $toPrice) {
                    return $item->price < $toPrice && $item->price >= $fromPrice;
                })->sum(fn($item) => $item->price * $item->quantity);
            }

            $price = $this->randomFrom($fromPrice, $toPrice, $currencyCoin->quotePrecision);
            $quoteQty = $this->randomFrom($minQuoteQty, $maxQuoteQty, $currencyCoin->quotePrecision);
            $qty = $this->truncate($quoteQty / $price, $currencyCoin->basePrecision);

            //PlaceAndCancel
            if ($i < $this->runOrderStepMax && $price > 0 && $quoteQty > 0 && $qty > 0) {
                $openOrder = $openOrders->first(fn($item) => $item->price >= $fromPrice && $item->price < $toPrice);
                if ($openOrder) {
                    $openOrders = $openOrders->reject(fn($item) => $item === $openOrder);

                    $this->cancelOrder($openOrder->id);
                    $this->placeLimitOrder($coin, $currency, Consts::ORDER_TRADE_TYPE_BUY, $qty, $price);
                }
            }

            $changeVol = BigNumber::new($targetVol)->sub($currentVol)->toString();
            if (BigNumber::new($changeVol)->abs()->toString() < BigNumber::new($minQuoteQty + $maxQuoteQty)->div(2)->toString()) {
                continue;
            }

            $countOrder = 0;
            if ($changeVol > 0) {
                while ($changeVol > 0 && ($limitOrder < 0 || $countOrder < $limitOrder)) {
                    $price = $this->randomFrom($fromPrice, $toPrice, $currencyCoin->quotePrecision);
                    $quoteQty = $this->randomFrom($minQuoteQty, $maxQuoteQty, $currencyCoin->quotePrecision);
                    $qty = $this->truncate($quoteQty / $price, $currencyCoin->basePrecision);
                    if ($price <= 0 || $quoteQty <= 0 || $qty <= 0) {
                        break;
                    }
                    $changeVol = BigNumber::new($changeVol)->sub($quoteQty)->toString();
                    $this->placeLimitOrder($coin, $currency, Consts::ORDER_TRADE_TYPE_BUY, $qty, $price);
                    $countOrder++;
                }
            } else {
                while ($changeVol < 0 && ($limitOrder < 0 || $countOrder < $limitOrder)) {
                    if ($openOrders->count() == 0) {
                        break;
                    }

                    $openOrder = $openOrders->first(fn($item) => $item->price >= $fromPrice && $item->price < $toPrice);
                    if (!$openOrder) {
                        break;
                    }

                    $openOrders = $openOrders->reject(fn($item) => $item === $openOrder);

                    $changeVol = BigNumber::new($changeVol)->add(BigNumber::new($openOrder->price)->mul(BigNumber::new($openOrder->quantity)->sub($openOrder->executed_quantity)))->toString();
                    $this->cancelOrder($openOrder->id);
                    $countOrder++;
                }
            }
        }

        return true;
    }


    private function testTask($currencyCoin, $refLastPrice, $minQuoteQty, $maxQuoteQty, $exchangeLastPrice, $orderBook, $coin, $currency)
    {
        $openOrders = $this->getOpenOrder(Consts::ORDER_TRADE_TYPE_BUY);
        $bidPercents = MasterdataService::getOneTable('bot_settings')
            ->filter(function ($value, $key) use ($currency, $coin) {
                return $value->currency == $currency && $value->coin == $coin;
            })
            ->pluck('bids')
            ->first();
        if ($bidPercents) {
            $bidPercents = json_decode($bidPercents, true);
        }
        if (!$bidPercents) {
            return false;
        }

        $limitOrder = env('FAKE_PLACE_ORDER_LIMIT', 2);
        for ($i = 0; $i < count($bidPercents); $i++) {
            $bidPercent = $bidPercents[$i][0];
            $targetVol = $bidPercents[$i][1];

            $toPrice = $i == 0 ? $refLastPrice : $refLastPrice - $refLastPrice * $bidPercents[$i - 1][0] / 100;
            $toPrice = $this->truncate($toPrice, $currencyCoin->quotePrecision);

            $fromPrice = $refLastPrice - $refLastPrice * $bidPercent/100;
            $fromPrice = $this->truncate($fromPrice, $currencyCoin->quotePrecision);

            $currentVol = 0;
            if ($orderBook['buy']) {
                $currentVol = $orderBook['buy']->filter(function ($item) use ($fromPrice, $toPrice) {
                    return $item->price < $toPrice && $item->price >= $fromPrice;
                })->sum(fn($item) => $item->price * $item->quantity);
            }

            $price = $this->randomFrom($fromPrice, $toPrice, $currencyCoin->quotePrecision);
            $quoteQty = $this->randomFrom($minQuoteQty, $maxQuoteQty, $currencyCoin->quotePrecision);
            $qty = $this->truncate($quoteQty / $price, $currencyCoin->basePrecision);

            //PlaceAndCancel
            if ($i < $this->runOrderStepMax && $price > 0 && $quoteQty > 0 && $qty > 0) {
                $openOrder = $openOrders->first(fn($item) => $item->price >= $fromPrice && $item->price < $toPrice);
                if ($openOrder) {
                    $openOrders = $openOrders->reject(fn($item) => $item === $openOrder);

                    //$this->cancelOrder($openOrder->id);
                    $this->placeLimitOrder($coin, $currency, Consts::ORDER_TRADE_TYPE_BUY, $qty, $price);
                }
            }

            $changeVol = BigNumber::new($targetVol)->sub($currentVol)->toString();
            if (BigNumber::new($changeVol)->abs()->toString() < BigNumber::new($minQuoteQty + $maxQuoteQty)->div(2)->toString()) {
                continue;
            }

            $countOrder = 0;
            if ($changeVol > 0) {
                while ($changeVol > 0 && ($limitOrder < 0 || $countOrder < $limitOrder)) {
                    $price = $this->randomFrom($fromPrice, $toPrice, $currencyCoin->quotePrecision);
                    $quoteQty = $this->randomFrom($minQuoteQty, $maxQuoteQty, $currencyCoin->quotePrecision);
                    $qty = $this->truncate($quoteQty / $price, $currencyCoin->basePrecision);
                    if ($price <= 0 || $quoteQty <= 0 || $qty <= 0) {
                        break;
                    }
                    $changeVol = BigNumber::new($changeVol)->sub($quoteQty)->toString();
                    $this->placeLimitOrder($coin, $currency, Consts::ORDER_TRADE_TYPE_BUY, $qty, $price);
                    $countOrder++;
                }
            } else {
               /* while ($changeVol < 0 && ($limitOrder < 0 || $countOrder < $limitOrder)) {
                    if ($openOrders->count() == 0) {
                        break;
                    }

                    $openOrder = $openOrders->first(fn($item) => $item->price >= $fromPrice && $item->price < $toPrice);
                    if (!$openOrder) {
                        break;
                    }

                    $openOrders = $openOrders->reject(fn($item) => $item === $openOrder);

                    $changeVol = BigNumber::new($changeVol)->add(BigNumber::new($openOrder->price)->mul(BigNumber::new($openOrder->quantity)->sub($openOrder->executed_quantity)))->toString();
                    $this->cancelOrder($openOrder->id);
                    $countOrder++;
                }*/
            }
        }

        return true;
    }

    private function askTask($currencyCoin, $refLastPrice, $minQuoteQty, $maxQuoteQty, $exchangeLastPrice, $orderBook, $coin, $currency)
    {
        $openOrders = $this->getOpenOrder(Consts::ORDER_TRADE_TYPE_SELL);
        $askPercents = MasterdataService::getOneTable('bot_settings')
            ->filter(function ($value, $key) use ($currency, $coin) {
                return $value->currency == $currency && $value->coin == $coin;
            })
            ->pluck('asks')
            ->first();
        if ($askPercents) {
            $askPercents = json_decode($askPercents, true);
        }

        if (!$askPercents) {
            return false;
        }

        $limitOrder = env('FAKE_PLACE_ORDER_LIMIT', 2);
        for ($i = 0; $i < count($askPercents); $i++) {
            $bidPercent = $askPercents[$i][0];
            $targetVol = $askPercents[$i][1];

            $fromPrice = $i == 0 ? $refLastPrice : $refLastPrice + $refLastPrice * $askPercents[$i - 1][0] / 100;
            $fromPrice = $this->truncate($fromPrice, $currencyCoin->quotePrecision);

            $toPrice = $refLastPrice + $refLastPrice * $bidPercent/100;
            $toPrice = $this->truncate($toPrice, $currencyCoin->quotePrecision);

            $currentVol = 0;
            if ($orderBook['sell']) {
                $currentVol = $orderBook['sell']->filter(function ($item) use ($fromPrice, $toPrice) {
                    return $item->price < $toPrice && $item->price >= $fromPrice;
                })->sum(fn($item) => $item->price * $item->quantity);
            }

            $price = $this->randomFrom($fromPrice, $toPrice, $currencyCoin->quotePrecision);
            $quoteQty = $this->randomFrom($minQuoteQty, $maxQuoteQty, $currencyCoin->quotePrecision);
            $qty = $this->truncate($quoteQty / $price, $currencyCoin->basePrecision);

            //PlaceAndCancel
            if ($i < $this->runOrderStepMax && $price > 0 && $quoteQty > 0 && $qty > 0) {
                $openOrder = $openOrders->first(fn($item) => $item->price >= $fromPrice && $item->price < $toPrice);
                if ($openOrder) {
                    $openOrders = $openOrders->reject(fn($item) => $item === $openOrder);
                    $this->cancelOrder($openOrder->id);
                    $this->placeLimitOrder($coin, $currency, Consts::ORDER_TRADE_TYPE_SELL, $qty, $price);
                }
            }

            $changeVol = BigNumber::new($targetVol)->sub($currentVol)->toString();
            if (BigNumber::new($changeVol)->abs()->toString() < BigNumber::new($minQuoteQty + $maxQuoteQty)->div(2)->toString()) {
                continue;
            }

            $countOrder = 0;
            if ($changeVol > 0) {
                while ($changeVol > 0 && ($limitOrder < 0 || $countOrder < $limitOrder)) {
                    $price = $this->randomFrom($fromPrice, $toPrice, $currencyCoin->quotePrecision);
                    $quoteQty = $this->randomFrom($minQuoteQty, $maxQuoteQty, $currencyCoin->quotePrecision);
                    $qty = $this->truncate($quoteQty / $price, $currencyCoin->basePrecision);
                    if ($price <= 0 || $quoteQty <= 0 || $qty <= 0) {
                        break;
                    }
                    $changeVol = BigNumber::new($changeVol)->sub($quoteQty)->toString();
                    $this->placeLimitOrder($coin, $currency, Consts::ORDER_TRADE_TYPE_SELL, $qty, $price);
                    $countOrder++;
                }
            } else {
                while ($changeVol < 0 && ($limitOrder < 0 || $countOrder < $limitOrder)) {
                    if ($openOrders->count() == 0) {
                        break;
                    }

                    $openOrder = $openOrders->first(fn($item) => $item->price >= $fromPrice && $item->price < $toPrice);
                    if (!$openOrder) {
                        break;
                    }

                    $openOrders = $openOrders->reject(fn($item) => $item === $openOrder);

                    $changeVol = BigNumber::new($changeVol)->add(BigNumber::new($openOrder->price)->mul(BigNumber::new($openOrder->quantity)->sub($openOrder->executed_quantity)))->toString();
                    $this->cancelOrder($openOrder->id);
                    $countOrder++;
                }
            }
        }

        return true;
    }

    private function cancelTask($currencyCoin, $refLastPrice, $minQuoteQty, $maxQuoteQty, $exchangeLastPrice, $orderBook, $coin, $currency)
    {
        $limitOrder = env('FAKE_CANCEL_ORDER_OLD_LIMIT', 2);
        $cancelOrder = $this->getCancelOrderAmount(1000, $limitOrder);
        foreach ($cancelOrder as $order) {
            try {
                $this->cancelOrder($order->id);
            } catch (Exception $ex) {}

        }

        $maxBibs = 10;
        $maxAsks = 10;
        $min = BigNumber::new($refLastPrice - $refLastPrice * $maxBibs / 100)->toString();
        $max = BigNumber::new($refLastPrice + $refLastPrice * $maxAsks / 100)->toString();
        $limitOrder = env('FAKE_CANCEL_ORDER_LIMIT', 2);

        if ($min > 0 || $max > 0) {
            $cancelOrders = $this->getCancelOrder($min, $max, $limitOrder);
            $countOrder = 0;
            foreach ($cancelOrders as $order) {
                if ($countOrder >= $limitOrder) {
                    break;
                }

                try {
                    $this->cancelOrder($order->id);
                } catch (Exception $ex) {}
                $countOrder++;
            }
        }
        return true;

    }
}
