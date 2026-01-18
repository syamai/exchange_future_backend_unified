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
use App\Models\Price;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SpotFakeData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $orderService;
    private $priceService;

    protected $currency;
    protected $coin;
    protected $lastRun;
    protected $checkingInterval;
    protected $redis;

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
        if (!isset(Consts::FAKE_CURRENCY_COINS[$this->coin.'_'.$this->currency])) {
            return;
        }
        $this->redis->set($this->getLastRunKey(), $this->lastRun);

        $timeSleep = env('FAKE_DATA_TIME_SLEEP_TRADE_SPOT', 200000); // 200ms
        while (true) {
            if ($this->lastRun + $this->checkingInterval - 500 < Utils::currentMilliseconds()) {
                // if last matching take more than 3s to finish
                // we need end this processor, because other processor has been started
                return;
            }

            if (Utils::currentMilliseconds() - $this->lastRun > $this->checkingInterval / 2) {
                //echo "\nlanvo:set redis";
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

    private function getLastRunKey(): string
    {
        return 'last_run_fake_data_' . $this->currency . '_' . $this->coin;
    }

    protected function doJob($currency = 'usdt', $coin = 'sol') {

        //echo "\nlanvo::SpotFakeData: ".$currency."-".$coin;
        //echo "\n";
        $fakeDataTradeSpot = env("FAKE_DATA_TRADE_SPOT", false);
        if ($fakeDataTradeSpot) {
            $keyFakeId = 'orderBook' . $currency . $coin . '_fake_id';
            $priceGroups = MasterdataService::getOneTable('price_groups')
                ->filter(function ($value, $key) use ($currency, $coin){
                    return $value->currency == $currency && $value->coin == $coin;
                })
                ->pluck('value');

            try {
                $symbolBinace = Consts::FAKE_CURRENCY_COINS[$coin.'_'.$currency];
                /*$client = new Client([
                    'base_uri' => Consts::DOMAIN_BINANCE_API
                ]);

                $response = $client->get('api/v3/depth', [
                    'query' => [
                        'symbol' => Consts::FAKE_CURRENCY_COINS[$coin.'_'.$currency],
                        'limit' => 2
                    ],
                    'timeout' => 5,
                    'connect_timeout' => 5,
                ]);

                $dataTrade = json_decode($response->getBody()->getContents());
                if (!empty($dataTrade->lastUpdateId)) {
                    $sendSocket = true;
                    if (Cache::has($keyFakeId)) {
                        $lastUpdateId = Cache::get($keyFakeId);
                        if ($lastUpdateId >= $dataTrade->lastUpdateId) {
                            $sendSocket = false;
                        }
                    }

                    if ($sendSocket) {
                        try {*/
                            //$loadCache = false;
                            //$price = $this->priceService->getSinglePrice($currency, $coin, $loadCache);

                            $client = new Client([
                                'base_uri' => Consts::DOMAIN_BINANCE_API
                            ]);

                            $response = $client->get('api/v3/trades', [
                                'query' => [
                                    'symbol' => $symbolBinace,
                                    'limit' => 10
                                ],
                                'timeout' => 5,
                                'connect_timeout' => 5,
                            ]);
                            $sendSocket = false;

                            $dataTrades = collect(json_decode($response->getBody()->getContents()));
                            if (!$dataTrades->isEmpty()) {
                                $keyOrderId = 'orderMatchId' . $currency . $coin;
                                $oldOrderId = 0;
                                $lastOrderId = 0;
                                if (Cache::has($keyOrderId)) {
                                    $oldOrderId = Cache::get($keyOrderId);
                                }

                                /*$priceTmp = [];
                                $keyPriceTmp = "";
                                foreach ($price as $k => $v) {
                                    $keyPriceTmp = $k;
                                    $priceTmp[$k] = $v;
                                    break;
                                }

                                $lastPrice = $price[$keyPriceTmp]->previous_price;*/
                                $priceNew = "";
                                $previousPrice = "";

                                foreach ($dataTrades as $trade) {
                                    $lastOrderId = $trade->id;
                                    if ($trade->id > $oldOrderId) {
                                        $qty = BigNumber::new($trade->qty)->div(5)->toString();
                                        $quoteQty = BigNumber::new($trade->quoteQty)->div(5)->toString();
                                        $orderTransaction = collect([
                                            'coin' => $coin,
                                            'currency' => $currency,
                                            'amount' => $quoteQty,
                                            'created_at' => $trade->time,
                                            'btc_amount' => 0,
                                            'buy_fee' => "0.0000000000",
                                            'executed_date' => Carbon::createFromTimestamp($trade->time/1000)->toISOString(),
                                            'price' => $trade->price,
                                            'quantity' => $qty,
                                            'sell_fee' => "0.0000000000",
                                            'status' => Consts::ORDER_STATUS_EXECUTED,
                                            'transaction_type' => !$trade->isBuyerMaker ? Consts::ORDER_TRADE_TYPE_BUY : Consts::ORDER_TRADE_TYPE_SELL,
                                            "buyer_email" => "",
                                            "seller_email" => ""
                                        ]);
                                        $buyOrder = collect([
                                            'base_price' => null,
                                            'coin' => $coin,
                                            'created_at' => $trade->time,
                                            'currency' => $currency,
                                            'executed_price' => "0.0000000000",
                                            'executed_quantity' => "0.0000000000",
                                            'fee' => "0.0000000000",
                                            'ioc' => null,
                                            'market_type' => 0,
                                            'original_id' => null,
                                            'price' => $trade->price,
                                            'quantity' => $qty,
                                            'status' => "pending",
                                            'stop_condition' => null,
                                            'trade_type' => "buy",
                                            'type' => "limit",
                                            'updated_at' => $trade->time,
                                            "email" => "",
                                        ]);
                                        $sellOrder = collect([
                                            'base_price' => null,
                                            'coin' => $coin,
                                            'created_at' =>  $trade->time,
                                            'currency' => $currency,
                                            'executed_price' => "0.0000000000",
                                            'executed_quantity' => "0.0000000000",
                                            'fee' => "0.0000000000",
                                            'ioc' => null,
                                            'market_type' => 0,
                                            'original_id' => null,
                                            'price' => $trade->price,
                                            'quantity' => $qty,
                                            'status' => "pending",
                                            'stop_condition' => null,
                                            'trade_type' => "sell",
                                            'type' => "limit",
                                            'updated_at' => $trade->time,
                                            "email" => "",
                                        ]);
                                        event(new OrderTransactionCreated($orderTransaction, $buyOrder, $sellOrder));

                                        //save prices
                                        $model = new Price();
                                        $model->price = $trade->price;
                                        $model->currency = $currency;
                                        $model->coin = $coin;
                                        $model->created_at = $trade->time;
                                        $model->quantity = $qty;
                                        $model->amount = $quoteQty;
                                        $model->is_crawled = Consts::TRUE;
                                        $model->is_buyer = $trade->isBuyerMaker;
                                        $model->setConnection('master');
                                        $model->save();

                                        $this->priceService->setCacheCurrentAndPreviousPrice($currency, $coin, $model);

                                        $minPriceKey = "Price:$currency:$coin:min";
                                        if (Cache::has($minPriceKey) && Cache::get($minPriceKey) > $model->price) {
                                            Cache::put($minPriceKey, $model->price, PriceService::PRICE_CACHE_LIVE_TIME);
                                        }
                                        $maxPriceKey = "Price:$currency:$coin:max";
                                        if (Cache::has($maxPriceKey) && Cache::get($maxPriceKey) < $model->price) {
                                            Cache::put($maxPriceKey, $model->price, PriceService::PRICE_CACHE_LIVE_TIME);
                                        }

                                        Cache::forget("Price:$currency:$coin:24hChange");

                                        //SendPrices::dispatchIfNeed($currency, $coin);

                                        $priceNew = $trade->price;
                                        if (!$previousPrice) {
                                            $previousPrice = $priceNew;
                                        }
                                        $sendSocket = true;

                                        /*$priceTmp[$keyPriceTmp]->previous_price = $lastPrice;
                                        $priceTmp[$keyPriceTmp]->price = $trade->price;
                                        $lastPrice = $trade->price;
                                        event(new PricesUpdated($priceTmp));*/
                                    }
                                }

                                //SendPrices::dispatchIfNeed($currency, $coin);
                                $price = $this->priceService->getSinglePrice($currency, $coin, false);
                                event(new PricesUpdated($price));

                                if ($oldOrderId < $lastOrderId) {
                                    Cache::forever($keyOrderId, $lastOrderId);
                                }

                                //$price[$keyPriceTmp]->previous_price = $lastPrice;
                                /*if ($priceNew) {
//                                    foreach ($price as $k => $v) {
//                                        $price[$k]->price = $priceNew;
//                                        if ($previousPrice) {
//                                            $price[$k]->previous_price = $previousPrice;
//                                        }
//                                    }
                                    $keyPriceCurrent = "Price:$currency:$coin:current";
                                    if (Cache::has($keyPriceCurrent)) {
                                        $priceCurrent = Cache::get($keyPriceCurrent);
                                        if (BigNumber::new($priceCurrent->price)->sub(BigNumber::new($priceNew))->toString() != 0) {
                                            Cache::forget($keyPriceCurrent);
                                        }
                                    }
                                }*/
                            }


//                            $keyPriceMatch = 'fakePriceOrderMatch' . $currency . $coin;
//                            if (Cache::has($keyPriceMatch)) {
//                                echo "\nfakePriceOrderMatch:" . Cache::get($keyPriceMatch);
//                            }
                            //event(new PricesUpdated($price));

                            if ($sendSocket) {
                                $autoMatching = true;
                                $orderBookSocket = null;
                                foreach ($priceGroups as $tickerSize) {
                                    $key = OrderService::getOrderBookKey($currency, $coin, $tickerSize);
                                    Cache::forget($key);
                                    if (!$orderBookSocket) {
                                        //SendOrderBook::dispatchIfNeed($currency, $coin, $tickerSize);
                                        $orderBook = $this->orderService->getOrderBook($currency, $coin, $tickerSize, $autoMatching);
                                        $orderBookSocket = $orderBook;
                                    }

                                    $autoMatching = false;
                                    if ($orderBookSocket && $orderBookSocket['buy'] && $orderBookSocket['sell']) {
                                        event(new OrderBookUpdated($orderBookSocket, $currency, $coin, $tickerSize, true));
                                    }
                                }
                            }


                        /*} catch (\Exception $e) {}
                    }
                }*/
            } catch (Exception $ex) {
                Log::error("SpotFakeData:fake:error");
                Log::error($ex);
                return false;
            }
        }
        return true;
    }

    private static function getRedisConnection()
    {
        return Consts::RC_ORDER_PROCESSOR;
    }
}
