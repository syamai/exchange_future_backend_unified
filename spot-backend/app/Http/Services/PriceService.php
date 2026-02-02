<?php

namespace App\Http\Services;

use App\Consts;
use App\Jobs\SendPrices;
use App\Jobs\SendPricesChange;
use App\Models\CoinSetting;
use App\Models\Price;
use App\Models\TmpPrice;
use App\Models\TotalPrice;
use App\Utils;
use App\Utils\BigNumber;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PriceService
{
    const PRICE_CACHE_LIVE_TIME = 10; // minutes
    const LIMIT_COIN_CHART = 4;

    //    const LIMIT_PRICE_FEATURE = 5;

    private function getInterval($resolution) {
        $interval = '1s';
        $resolution = $resolution / 1000;
        if ($resolution < 60) {
            return $interval;
        }
        $resolution = $resolution / 60;
        if ($resolution < 60) {
            $interval = '1m';
            if (in_array($resolution, [1, 3, 5, 15, 30])) {
                $interval = $resolution.'m';
            }
            return $interval;
        }

        $resolution = $resolution / 60;
        if ($resolution < 24) {
            $interval = '1h';
            if (in_array($resolution, [1, 2, 4, 6, 8, 12])) {
                $interval = $resolution.'h';
            }
            return $interval;
        }

        $resolution = $resolution / 24;

        if ($resolution < 7) {
            $interval = '1d';
            if (in_array($resolution, [1, 3])) {
                $interval = $resolution.'d';
            }
            return $interval;
        }

        $interval = '1w';

        if ($resolution >= 30) {
            $interval = '1M';
        }

        return $interval;
    }

    public function getBars($params)
    {
        $startTime = (int)$params['from'] * 1000;
        $endTime = (int)$params['to'] * 1000;
        $resolution = (int)$params['resolution'];
        $currency = $params['currency'];
        $coin = $params['coin'];

        $fakeDataTradeSpot = env("FAKE_DATA_TRADE_SPOT", false);
        $fakeChartTradeSpot = env("FAKE_CHART_TRADE_SPOT", true);
        $result = [];
        if ($fakeDataTradeSpot && $fakeChartTradeSpot && isset(Consts::FAKE_CURRENCY_COINS[$coin.'_'.$currency])) {
            try {
                $interval = $this->getInterval($resolution);
                $client = new Client([
                    'base_uri' => Consts::DOMAIN_BINANCE_API
                ]);
                $response = $client->get('api/v3/klines', [
                    'query' => [
                        'symbol' => Consts::FAKE_CURRENCY_COINS[$coin.'_'.$currency],
                        'interval' => $interval,
                        //'startTime' => $startTime,
                        //'endTime' => $endTime,
                        //'timeZone' => 1,
                        'limit' => 500
                    ],
                    'timeout' => 5,
                    'connect_timeout' => 5,
                ]);

                $dataCharts = collect(json_decode($response->getBody()->getContents()));
                //$resultBb = $this->getCandleCharBarsInfoDb($startTime, $endTime, $resolution, $currency, $coin, true);

                $resultTmp = [];
                if (!$dataCharts->isEmpty()) {
                    $minTime = -1;
                    $maxTime = -1;
                    foreach ($dataCharts as $chart) {
                        $time = $chart[6]; //intval(floor($chart[6]/$resolution) * $resolution);
                        if ($minTime < 0 || $time < $minTime) {
                            $minTime = $time;
                        }
                        if ($maxTime < 0 || $time > $maxTime) {
                            $maxTime = $time;
                        }


                        $resultTmp[] = (object)[
                            'close' => $chart[4],
                            'closing_time' => $chart[6],
                            'high' => $chart[2],
                            'low' => $chart[3],
                            'open' => $chart[1],
                            'opening_time' => $chart[0],
                            'time' => $time,
                            'volume' => $chart[5],
                        ];

                    }

//                    if ($resultBb) {
//                        foreach ($resultBb as $r) {
//                            if ($r->time > $minTime && $r->time < $maxTime) {
//                                $resultTmp[] = $r;
//                            }
//                        }
//                    }

                    if ($resultTmp) {
                        $resultTmp = collect($resultTmp)->sortBy('time')->keyBy('time');
                    }

                    $lastPrice = 0;
                    foreach ($resultTmp as $r) {
//                        if ($lastPrice == 0) {
//                            $lastPrice = $r->open;
//                        }
//                        $r->open = $lastPrice;
//                        $lastPrice = $r->close;
//                        $r->open = $lastPrice;
                        $result[] = $r;
                    }
                }

            } catch (\Exception $e) {}
        }

        if (!$result) {
            $result = $this->getCandleChartBars($startTime, $endTime, $resolution, $currency, $coin);
        }

        $arr = [];
        if (isset($params['market_type']) && $params['market_type'] == 1) {
            foreach ($result as $key => $value) {
                $elem = [];
                $elem[] = (int)$value->closing_time;
                $elem[] = (int)$value->close;
                $arr[] = $elem;
            }
            return $arr;
        }
        return $result;
    }

    public function getBarsNew($params) {
        $startTime = (int)$params['from'] * 1000;
        $endTime = (int)$params['to'] * 1000;
        $resolution = (int)$params['resolution'];
        $currency = $params['currency'];
        $coin = $params['coin'];

        $interval = $this->getInterval($resolution);
        $result = $this->getCandleChartBarsNew($startTime, $endTime, $resolution, $currency, $coin, $interval);

        if (isset($params['market_type']) && $params['market_type'] == 1) {
            $arr = [];
            foreach ($result as $key => $value) {
                $elem = [];
                $elem[] = (int)$value->closing_time;
                $elem[] = (int)$value->close;
                $arr[] = $elem;
            }
            return $arr;
        }
        return $result;


    }

    private function getCandleCharBarsInfoDb($startTime, $endTime, $resolution, $currency, $coin, $getInfo = false) {
        $key = "Chart:bars:$currency:$coin:$resolution";
        if ($getInfo) {
            $key = "Fake:Chart:bars:$currency:$coin:$resolution";
        }

        $cacheStartTime = $endTime;
        $cacheEndTime = $startTime;

        $cachedBars = [];
        if (Cache::has($key)) {
            $cachedResult = Cache::get($key);
            if (!$getInfo) {
                $cacheStartTime = $cachedResult['start_time'];
                $cacheEndTime = $cachedResult['end_time'];
                $cachedBars = $cachedResult['bars'];
                if ($startTime < $cacheStartTime && $endTime > $cacheEndTime) { //larger than cache in both ends
                    $part1 = $this->getCandleChartBars($startTime, $cacheStartTime - 1, $resolution, $currency, $coin);
                    $part2 = $this->getCandleChartBars($cacheEndTime + 1, $endTime, $resolution, $currency, $coin);
                    $result = array_merge($part1, $cachedBars, $part2);
                    return $this->extractBars($result, $startTime, $endTime, $resolution, $currency, $coin);
                }
            } else {
                return $cachedResult['bars'];
            }

        }

        $chartStartTime = $this->getChartStartTime($cacheStartTime, $cacheEndTime, $startTime);
        $chartEndTime = $this->getChartEndTime($cacheStartTime, $cacheEndTime, $endTime);

        $newBars = DB::select(
            'select hashed_prices.min_price as low, hashed_prices.max_price as high, opening_prices.price as open, closing_prices.price as close, CONVERT(time*?, SIGNED) as time, volume, opening_time, closing_time
            from (select min(prices.price) as min_price, max(prices.price) as max_price, min(prices.created_at) as opening_time, max(prices.created_at) as closing_time, floor(prices.created_at/?) as time, sum(quantity) as volume from prices where prices.currency=? and prices.coin=? and prices.created_at >= ? and prices.created_at <= ? group by time) as hashed_prices
            join prices as opening_prices on opening_prices.created_at = opening_time
                and opening_prices.currency=? and opening_prices.coin=?
            join prices as closing_prices on closing_prices.created_at = closing_time
                and closing_prices.currency=? and closing_prices.coin=?',
            [
                $resolution,
                $resolution,
                $currency,
                $coin,
                $chartStartTime,
                $chartEndTime,
                $currency,
                $coin,
                $currency,
                $coin
            ]
        );

        $result = $cachedBars;
        if (!empty($newBars)) {
            $minTime = min($startTime, $cacheStartTime);
            if (empty($cachedBars)) { //no cache
                $result = $newBars;
            } elseif ($minTime == $startTime) { //get older than in cache
                $result = $this->mergeBars($newBars, $cachedBars);
            } else {
                $result = $this->mergeBars($cachedBars, $newBars);
            }

            $cachedBars = $result;
            if (count($result) > Consts::MAX_CHART_BARS_LENGTH) {
                $cachedBars = array_slice($result, -Consts::MAX_CHART_BARS_LENGTH);
            }

            $minTime = $cachedBars[0]->opening_time;

            $lastBar = end($cachedBars);
            $maxTime = $lastBar->closing_time;

            Cache::forever($key, ['start_time' => $minTime, 'end_time' => $maxTime, 'bars' => $cachedBars]);
        }
        return $result;
    }

    private function getCandleCharBarsInfoDbNew($startTime, $endTime, $resolution, $currency, $coin, $interval) {
        $key = "Chart:bars:new:$currency:$coin:$resolution";

        $cacheStartTime = $endTime;
        $cacheEndTime = $startTime;

        $cachedBars = [];
        $cacheChartNew = env("CACHE_CHART_TABLE_SYMBOL_SPOT", false);
        if ($cacheChartNew && Cache::has($key)) {
            $cachedResult = Cache::get($key);
            $cacheStartTime = $cachedResult['start_time'];
            $cacheEndTime = $cachedResult['end_time'];
            $cachedBars = $cachedResult['bars'];
            if ($startTime < $cacheStartTime && $endTime > $cacheEndTime) { //larger than cache in both ends
                $part1 = $this->getCandleChartBarsNew($startTime, $cacheStartTime - 1, $resolution, $currency, $coin, $interval);
                $part2 = $this->getCandleChartBarsNew($cacheEndTime - $resolution, $endTime, $resolution, $currency, $coin, $interval);
                $result = array_merge($part1, $cachedBars, $part2);
                return $this->extractBarsNew($result, $startTime, $endTime, $resolution, $currency, $coin);
            }
        }

        $chartStartTime = $this->getChartStartTime($cacheStartTime, $cacheEndTime, $startTime);
        $chartEndTime = $this->getChartEndTime($cacheStartTime, $cacheEndTime, $endTime);

        /*$newBars = DB::select(
            'select hashed_prices.min_price as low, hashed_prices.max_price as high, opening_prices.price as open, closing_prices.price as close, CONVERT(time*?, SIGNED) as time, volume, opening_time, closing_time
            from (select min(prices.price) as min_price, max(prices.price) as max_price, min(prices.created_at) as opening_time, max(prices.created_at) as closing_time, floor(prices.created_at/?) as time, sum(quantity) as volume from prices where prices.currency=? and prices.coin=? and prices.created_at >= ? and prices.created_at <= ? group by time) as hashed_prices
            join prices as opening_prices on opening_prices.created_at = opening_time
                and opening_prices.currency=? and opening_prices.coin=?
            join prices as closing_prices on closing_prices.created_at = closing_time
                and closing_prices.currency=? and closing_prices.coin=?',
            [
                $resolution,
                $resolution,
                $currency,
                $coin,
                $chartStartTime,
                $chartEndTime,
                $currency,
                $coin,
                $currency,
                $coin
            ]
        );*/
        $table = strtolower("klines_{$coin}_{$currency}");
        if(!Schema::hasTable($table)) {
            return [];
        }

        $newBars = DB::table($table)
            ->select(['low', 'high', 'open', 'close', 'time', DB::raw('quote_volume as volume'), 'opening_time', 'closing_time'])
            ->where('time', '>=', $chartStartTime)
            ->where('time', '<=', $chartEndTime)
            ->where('interval', 'like binary', $interval)
			->orderBy('time')
            ->get();


        $result = $cachedBars;
        if ($newBars->isNotEmpty()) {
            $newBars = $newBars->toArray();
            $minTime = min($startTime, $cacheStartTime);
            if (empty($cachedBars)) { //no cache
                $result = $newBars;
            } elseif ($minTime == $startTime) { //get older than in cache
                $result = $this->mergeBarsNew($newBars, $cachedBars);
            } else {
                $result = $this->mergeBarsNew($cachedBars, $newBars);
            }

            $cachedBars = $result;
            if (count($result) > Consts::MAX_CHART_BARS_LENGTH) {
                $cachedBars = array_slice($result, -Consts::MAX_CHART_BARS_LENGTH);
            }

            $minTime = $cachedBars[0]->time;

            $lastBar = end($cachedBars);
            $maxTime = $lastBar->time;

            Cache::forever($key, ['start_time' => $minTime, 'end_time' => $maxTime, 'bars' => $cachedBars]);
        }
        return $result;
    }

    private function getCandleChartBars($startTime, $endTime, $resolution, $currency, $coin)
    {
        $result = $this->getCandleCharBarsInfoDb($startTime, $endTime, $resolution, $currency, $coin);
        return $this->extractBars($result, $startTime, $endTime, $resolution, $currency, $coin);
    }

    private function getCandleChartBarsNew($startTime, $endTime, $resolution, $currency, $coin, $interval)
    {
        $result = $this->getCandleCharBarsInfoDbNew($startTime, $endTime, $resolution, $currency, $coin, $interval);
        return $this->extractBarsNew($result, $startTime, $endTime, $resolution, $currency, $coin);
    }


    private function getChartStartTime($cacheStartTime, $cacheEndTime, $startTime)
    {
        if ($startTime < $cacheStartTime) {
            return $startTime;
        }

        return $cacheEndTime + 1;
    }

    private function getChartEndTime($cacheStartTime, $cacheEndTime, $endTime)
    {
        if ($endTime > $cacheEndTime) {
            return $endTime;
        }

        return $cacheStartTime - 1;
    }

    private function mergeBars($oldBars, $newBars)
    {
        $lastOldBar = end($oldBars);
        $firstNewBar = $newBars[0];
        if ($lastOldBar->time == $firstNewBar->time) {
            array_pop($oldBars); //remove the last element
            $firstNewBar->low = BigNumber::min($firstNewBar->low, $lastOldBar->low);
            $firstNewBar->high = BigNumber::max($firstNewBar->high, $lastOldBar->high);
            $firstNewBar->open = $lastOldBar->open;
            $firstNewBar->volume = BigNumber::new($firstNewBar->volume)->add($lastOldBar->volume)->toString();
            $firstNewBar->opening_time = $lastOldBar->opening_time;
        } elseif ($lastOldBar->time > $firstNewBar->time) {
            Log::error("Something wrong with calculating the chart bars");
        }
        return array_merge($oldBars, $newBars);
    }

    private function mergeBarsNew($oldBars, $newBars)
    {
        $lastOldBar = end($oldBars);
        $firstNewBar = $newBars[0];
        if ($lastOldBar->time == $firstNewBar->time) {
            array_pop($oldBars); //remove the last element
            /*$firstNewBar->low = BigNumber::min($firstNewBar->low, $lastOldBar->low);
            $firstNewBar->high = BigNumber::max($firstNewBar->high, $lastOldBar->high);
            $firstNewBar->open = $lastOldBar->open;
            $firstNewBar->volume = BigNumber::new($firstNewBar->volume)->add($lastOldBar->volume)->toString();
            $firstNewBar->opening_time = $lastOldBar->opening_time;*/
        } elseif ($lastOldBar->time > $firstNewBar->time) {
            Log::error("Something wrong with calculating the chart bars");
        }
        return array_merge($oldBars, $newBars);
    }

    private function extractBars($bars, $startTime, $endTime, $resolution, $currency, $coin)
    {
        $lastPrice = $this->getPriceAt($currency, $coin, $startTime)->price;
        $startTime = (int)floor($startTime / $resolution) * $resolution;
        $endTime = (int)floor($endTime / $resolution) * $resolution;
        $bars = collect($bars)->keyBy('time');
        $result = [];
        for ($time = $startTime; $time <= $endTime; $time += $resolution) {
            if ($bars->has($time)) {
                $bar = $bars[$time];
                $bar->open = $lastPrice;
                $result[] = $bar;
                $lastPrice = $bars[$time]->close;
            } else {
                $result[] = (object)[
                    'low' => $lastPrice,
                    'high' => $lastPrice,
                    'open' => $lastPrice,
                    'close' => $lastPrice,
                    'volume' => '0',
                    'time' => $time,
                    'opening_time' => $time,
                    'closing_time' => $time
                ];
            }
        }
        return $result;
    }

    private function extractBarsNew($bars, $startTime, $endTime, $resolution, $currency, $coin)
    {
        $lastPrice = $bars ? $bars[0]->open : 0;
        $startTime = (int)floor($startTime / $resolution) * $resolution;
        $endTime = (int)floor($endTime / $resolution) * $resolution;
        $bars = collect($bars)->keyBy('time');
        $result = [];
        for ($time = $startTime; $time <= $endTime; $time += $resolution) {
            if ($bars->has($time)) {
                $bar = $bars[$time];
                //$bar->open = $lastPrice;
                $result[] = $bar;
                //$lastPrice = $bars[$time]->close;
            }/* else {
                $result[] = (object)[
                    'low' => $lastPrice,
                    'high' => $lastPrice,
                    'open' => $lastPrice,
                    'close' => $lastPrice,
                    'volume' => '0',
                    'time' => $time,
                    'opening_time' => $time,
                    'closing_time' => $time
                ];
            }*/
        }
        return $result;
    }

    public function getPrices($req = null)
    {
        $prices = [];
        $pricesByCoin = [];
        $curencyCoins = MasterdataService::getOneTable('coin_settings');

        foreach ($curencyCoins as $currencyCoin) {
            $currency = $currencyCoin->currency;
            $coin = $currencyCoin->coin;
            if ($coin == Consts::CURRENCY_USDT && $currency == Consts::CURRENCY_USD) {
                $key = $this->getPriceKey($currency, $coin);
                $item = (object)[
                    'coin' => $coin,
                    'currency' => $currency,
                    'price' => 1,
                    'change' => 0,
                    'time' => 0,
                    'last_24h_price' => 1,
                    'volume' => 0,
                    'created_at' => Utils::currentMilliseconds()
                ];
                if ($req && strtolower($currency) == strtolower($req)) {
                    $pricesByCoin[$key] = $item;
                } else {
                    $prices[$key] = $item;
                }
            } elseif ($currencyCoin->is_enable) {
                if ($req && strtolower($currency) == strtolower($req)) {
                    $key = $this->getPriceKey($currency, $coin);
                    $pricesByCoin[$key] = $this->getPrice($currency, $coin);
                } else {
                    $key = $this->getPriceKey($currency, $coin);
                    $prices[$key] = $this->getPrice($currency, $coin);
                }
            }

        }

        if (!$req) {
            if (!isset($prices['usd_btc']) && isset($prices['usdt_btc'])) {
                $usdbtc = (array) $prices['usdt_btc'];
                $usdbtc = (object) $usdbtc;
                $usdbtc->currency = 'usd';
                $prices['usd_btc'] = $usdbtc;
            }
        }
        return $req ? $pricesByCoin : $prices;
    }

    public function toUsdAmount($currency, $amount)
    {
        if ($currency === Consts::CURRENCY_USD) {
            return $amount;
        }
        $currentPrice = $this->getCurrentPrice('usd', $currency);
        if ($currentPrice) {
            return BigNumber::new($currentPrice->price)->mul($amount)->toString();
        }
        return '0';
    }

    public function getSinglePrice($currency, $coin, $loadCache = true)
    {
        $key = $this->getPriceKey($currency, $coin);
        $data = $this->getPrice($currency, $coin, $loadCache);

        return [$key => $data];
    }

    private function getPriceKey($currency, $coin)
    {
        return $currency . '_' . $coin;
    }

    public function getPrice($currency, $coin, $loadCache = true)
    {
        $fakeDataTradeSpot = env("FAKE_DATA_TRADE_SPOT", false);
        if ($fakeDataTradeSpot && isset(Consts::FAKE_CURRENCY_COINS[$coin.'_'.$currency])) {
            try {
                $key = "GetPriceFake:$currency:$coin:current";
                $resultPrice = $loadCache ? Cache::get($key) : null;
                if (!$resultPrice) {
                    $client = new Client([
                        'base_uri' => Consts::DOMAIN_BINANCE_API
                    ]);

                    $response = $client->get('api/v3/trades', [
                        'query' => [
                            'symbol' => Consts::FAKE_CURRENCY_COINS[$coin.'_'.$currency],
                            'limit' => 30
                        ],
                        'timeout' => 5,
                        'connect_timeout' => 5,
                    ]);

                    $dataTrades = collect(json_decode($response->getBody()->getContents()))->sortByDesc('id');
                    if (!$dataTrades->isEmpty()) {
                        $preTrade = $dataTrades->get($dataTrades->count() - 2)?? $dataTrades->get($dataTrades->count() - 1) ;

                        // get volume 24h
                        $client = new Client([
                            'base_uri' => Consts::DOMAIN_BINANCE_API
                        ]);

                        $response = $client->get('api/v3/ticker/24hr', [
                            'query' => [
                                'symbol' => Consts::FAKE_CURRENCY_COINS[$coin.'_'.$currency]
                            ],
                            'timeout' => 5,
                            'connect_timeout' => 5,
                        ]);

                        $volume = null;
                        $last24hPrice = null;
                        $ticker24H = collect(json_decode($response->getBody()->getContents()));
                        if (!$ticker24H->isEmpty()) {
                            $volume = BigNumber::new($ticker24H->get('quoteVolume'))->div(10)->toString() ;
                            $last24hPrice = $ticker24H->get('openPrice');
                        }

                        $keyPriceMatch = 'fakePriceOrderMatch' . $currency . $coin;
                        Cache::forever($keyPriceMatch, $dataTrades->first()->price);

                        $resultPrice = [
                            'coin' => $coin,
                            'currency' => $currency,
                            'price' => $dataTrades->first()->price,
                            'previous_price' => $preTrade ? $preTrade->price : $dataTrades->first()->price,
                            'change' => BigNumber::new($dataTrades->first()->price)->sub($dataTrades->last()->price)->div($dataTrades->last()->price)->mul(100)->toString(),
                            'last_24h_price' => $last24hPrice ?? $dataTrades->last()->price,
                            'volume' => $volume ?? $dataTrades->sum('quoteQty'),
                            'created_at' => Utils::currentMilliseconds(),
                        ];

                        Cache::forever($key, $resultPrice);
                    }
                }
                return (object) $resultPrice;
            } catch (\Exception $e) {
                Log::error("getPrice:fake:error");
                Log::error($e);
            }
        }

        $last24hPrice = 0;
        $change = 0;
        $currentPrice = $this->getCurrentPrice($currency, $coin, $loadCache);
        $previousPrice = $this->getPreviousPrice($currency, $coin, $loadCache);
        $lastPrice = $this->getLast24hPrice($currency, $coin, $loadCache);

        if ($lastPrice) {
            $last24hPrice = $lastPrice->price;
            if ($lastPrice->price != 0) {
                $change = BigNumber::new($currentPrice->price)->sub($lastPrice->price)->div($lastPrice->price)->mul(100)->toString();
            }
        }

        if ($currentPrice) {
            $price = $currentPrice->price;
            $keyPriceMatch = 'fakePriceOrderMatch' . $currency . $coin;
            Cache::forever($keyPriceMatch, $price);
            $previousPrice = @$previousPrice->price;
            $volume = $this->get24hVolumes($currency, $coin, $loadCache);
            return (object)[
                'coin' => $coin,
                'currency' => $currency,
                'price' => $price,
                'previous_price' => $previousPrice ?? $price,
                'change' => $change,
                'last_24h_price' => $last24hPrice,
                'volume' => $volume,
                'created_at' => $currentPrice->created_at,
            ];
        }

        return (object)[
            'coin' => $coin,
            'currency' => $currency,
            'price' => 0,
            'change' => 0,
            'time' => 0,
            'last_24h_price' => 0,
            'volume' => 0,
            'created_at' => Utils::currentMilliseconds()
        ];
    }

    public function setCacheCurrentAndPreviousPrice($currency, $coin, $result): void
    {
        $keyCurrentPrice = "Price:$currency:$coin:current";
        $keyPreviousPrice = "Price:$currency:$coin:previous";
        $oldCurrentPrice = Cache::get($keyCurrentPrice) ?? null;
        if (!$oldCurrentPrice) {
            $data = Price::on('master')->where('currency', $currency)
                ->where('coin', $coin)
                ->orderBy('created_at', 'desc')
                ->limit(2)
                ->get();
            $result = $data[0] ?? null;
            $oldCurrentPrice = $data[1] ?? null;

            if (!$result) {
                $result = (object)[
                    'currency' => $currency,
                    'coin' => $coin,
                    'price' => 0,
                    'amount' => 0,
                    'quantity' => 0,
                    'created_at' => Utils::currentMilliseconds()
                ];
            }

            if (!$oldCurrentPrice) {
                $oldCurrentPrice = (object)[
                    'currency' => $currency,
                    'coin' => $coin,
                    'price' => 0,
                    'amount' => 0,
                    'quantity' => 0,
                    'created_at' => Utils::currentMilliseconds()
                ];
            }

        }
        Cache::forever($keyCurrentPrice, $result);
        Cache::forever($keyPreviousPrice, $oldCurrentPrice);
    }

    public function getPreviousPrice($currency, $coin, $loadCache = true)
    {
        $hasPair = $this->hasPairCurrencyAndCoin($currency, $coin);
        $queryCurrency = $currency;
        $queryCoin = $coin;
        if (!$hasPair) {
            $queryCurrency = $coin;
            $queryCoin = $currency;
        }

        $key = "Price:$currency:$coin:previous";
        $result = $loadCache ? Cache::get($key) : null;
        if (!$result) {
            $result = Price::on('master')->where('currency', $queryCurrency)
                ->where('coin', $queryCoin)
                ->orderBy('created_at', 'desc')
                ->limit(2)
                ->get();
            $result = @$result[1] ?? null;  // Get previous price

            if (!$hasPair && $result) {
                $result->currency = $currency;
                $result->coin = $coin;
                $result->price = BigNumber::new(1)->div($result->price);
            }

            if (!$result) {
                $result = (object)[
                    'currency' => $currency,
                    'coin' => $coin,
                    'price' => 0,
                    'amount' => 0,
                    'quantity' => 0,
                    'created_at' => Utils::currentMilliseconds()
                ];
            }

            Cache::put($key, $result, PriceService::PRICE_CACHE_LIVE_TIME);
            //Cache::forever($key, $result);
        }
        return $result;
    }

    public function getCurrentPrice($currency, $coin, $loadCache = true)
    {
        $hasPair = $this->hasPairCurrencyAndCoin($currency, $coin);
        $queryCurrency = $currency;
        $queryCoin = $coin;
        if (!$hasPair) {
            $queryCurrency = $coin;
            $queryCoin = $currency;
        }

        $key = "Price:$currency:$coin:current";
        $result = $loadCache ? Cache::get($key) : null;
        if (!$result) {
            $result = Price::on('master')->where('currency', $queryCurrency)
                ->where('coin', $queryCoin)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$hasPair && $result) {
                $result->currency = $currency;
                $result->coin = $coin;
                $result->price = BigNumber::new(1)->div($result->price);
            }

            if (!$result) {
                $result = (object)[
                    'currency' => $currency,
                    'coin' => $coin,
                    'price' => 0,
                    'amount' => 0,
                    'quantity' => 0,
                    'created_at' => Utils::currentMilliseconds()
                ];
            }

            Cache::put($key, $result, PriceService::PRICE_CACHE_LIVE_TIME);
            //Cache::forever($key, $result);
        }
        return $result;
    }

    public function getCurrentPrice24h($currency, $coin, $loadCache = true)
    {

        $hasPair = $this->hasPairCurrencyAndCoin($currency, $coin);
        $queryCurrency = $currency;
        $queryCoin = $coin;

        //if the input is BTC/USD or BTC/USDT we will skip the reverse cases
        $onlyOnePairAvail = (($currency == Consts::CURRENCY_USDT && $coin == Consts::CURRENCY_BTC) || ($currency == Consts::CURRENCY_USD && $coin == Consts::CURRENCY_BTC));

        if (!$hasPair && !$onlyOnePairAvail) {
            $queryCurrency = $coin;
            $queryCoin = $currency;
        };


        $key = "Price:$currency:$coin:current";
        $result = $loadCache ? Cache::get($key) : null;
        if (!$result) {
            $result = DB::connection('master')->table(DB::raw('order_transactions FORCE INDEX (trade_history)'))
                ->where('currency', $queryCurrency)
                ->where('coin', $queryCoin)
                ->orderBy('id', 'desc')
                ->first();
            if (!$hasPair && $result && !$onlyOnePairAvail) {
                $result->currency = $currency;
                $result->coin = $coin;
                $result->price = BigNumber::new(1)->div($result->price);
            }

            if (isset($result) && $result->created_at < Utils::previous24hInMillis()) {
                $result->price = 0;
                return $result;
            }

            if (!isset($result)) {
                return (object)[
                    "price" => 0
                ];
            }
//            Cache::put($key, $result, PriceService::PRICE_CACHE_LIVE_TIME);
            Cache::forever($key, $result);
        }
        return $result;
    }

    public function getBeforePriceCircuitPrice($coinPairSetting, $rangeListenTime = 1, $orderExecutedAt = null)
    {
        $coin = $coinPairSetting->coin;
        $currency = $coinPairSetting->currency;
        if (!$orderExecutedAt) {
            $orderExecutedAt = now()->timestamp * 1000;
        }

        // 1 hour = 3600 seconds = 3600000 milliseconds
        $fluctuationsTime = $orderExecutedAt - ($rangeListenTime * 3600000);

        $price1 = Price::on('master')->where('currency', $currency)
            ->where('coin', $coin)
            ->where('created_at', '>=', $fluctuationsTime)
            ->where('created_at', '<=', $orderExecutedAt)
            ->orderBy('created_at', 'asc')
            ->first();

        $price2 = Price::on('master')->where('currency', $currency)
            ->where('coin', $coin)
            ->where('created_at', '>=', $fluctuationsTime)
            ->where('created_at', '<=', $orderExecutedAt)
            ->orderBy('created_at', 'desc')
            ->first();

        // Only has 1 price record between: $orderExecutedAt - $fluctuationsTime
        // Try get lastest price record
        if ($price1->created_at == $price2->created_at) {
            return $this->getLastestPriceWithRestrict($currency, $coin, $price2->created_at);
        }

        return @$price1->price ?? 0;
    }

    public function getLastestPriceWithRestrict($currency, $coin, $createdAtRestrict)
    {
        $result = Price::where('currency', $currency)
            ->where('coin', $coin)
            ->where('created_at', '<', $createdAtRestrict)
            ->orderBy('created_at', 'desc')
            ->first();

        return @$result->price;
    }

    public function getLastestPrice($currency, $coin)
    {
        $result = Price::where('currency', $currency)
            ->where('coin', $coin)
            ->orderBy('created_at', 'desc')
            ->first();

        return @$result->price;
    }

    private function hasPairCurrencyAndCoin($currency, $coin)
    {
        $currencyCoins = MasterdataService::getOneTable('coin_settings');
        $coinSetting = $currencyCoins->where('currency', $currency)->where('coin', $coin)->first();
        return !!$coinSetting;
    }

    //return the price from beginning of today
    public function getLast24hPrice($currency, $coin, $loadCache = true)
    {
        $key = "Price:$currency:$coin:yesterday";
        $result = $loadCache ? Cache::get($key) : null;
        if (!$result) {
            /*$result = Price::where('currency', $currency)
                ->where('coin', $coin)
                ->where('created_at', '>=', Utils::previous24hInMillis())
                ->orderBy('created_at', 'asc')
                ->first();*/
            $result = TmpPrice::where('currency', $currency)
                ->where('coin', $coin)
                ->orderBy('created_at', 'asc')
                ->first();

            if (is_null($result)) {
                $result = $this->getCurrentPrice($currency, $coin); //if there is no transaction in the last 24 hours
            }

            Cache::put($key, $result, PriceService::PRICE_CACHE_LIVE_TIME);
        }
        return $result;
    }

    public function getPricesAt($timestamp)
    {
        $prices = [];
        $curencyCoins = MasterdataService::getOneTable('coin_settings');
        foreach ($curencyCoins as $currencyCoin) {
            $currency = $currencyCoin->currency;
            $coin = $currencyCoin->coin;
            $key = $this->getPriceKey($currency, $coin);
            $prices[$key] = $this->getPriceAt($currency, $coin, $timestamp);
        }

        return $prices;
    }

    public function getPriceAt($currency, $coin, $timestamp)
    {
        // TODO should cache?
        $price = Price::where('currency', $currency)
            ->where('coin', $coin)
            ->where('created_at', '<=', $timestamp)
            ->orderBy('created_at', 'desc')
            ->first();
//        $maxId = Price::where('currency', $currency)
//            ->where('coin', $coin)
//            ->where('created_at', '<=', $timestamp)
//            ->max('id');


//        $price = null;
//        if ($maxId) {
//            $price = Price::where('id', $maxId)->first();
//        }

        if (!$price) {
            $price = (object)[
                'currency' => $currency,
                'coin' => $coin,
                'price' => 0
            ];
        }

        return $price;
    }

    public function updatePrice($trade)
    {
        $price = new Price();
        $price->price = $trade->price;
        $price->currency = $trade->currency;
        $price->coin = $trade->coin;
        $price->created_at = $trade->created_at;
        $price->quantity = $trade->quantity;
        $price->amount = $trade->amount;
        $price->is_buyer = $trade->transaction_type == Consts::ORDER_TRADE_TYPE_BUY ? 0 : 1;
        $price->save();
        $this->saveCurrentPriceToCache($price);

        SendPrices::dispatchIfNeed($trade->currency, $trade->coin);
		SendPricesChange::dispatchIfNeed("send");
        return $price->id;
    }

    private function saveCurrentPriceToCache($price)
    {
        $currency = $price->currency;
        $coin = $price->coin;
//        Cache::put("Price:$currency:$coin:current", PriceService::PRICE_CACHE_LIVE_TIME);
        $this->setCacheCurrentAndPreviousPrice($currency, $coin, $price);

        $minPriceKey = "Price:$currency:$coin:min";
        if (Cache::has($minPriceKey) && Cache::get($minPriceKey) > $price->price) {
            Cache::put($minPriceKey, $price->price, PriceService::PRICE_CACHE_LIVE_TIME);
        }
        $maxPriceKey = "Price:$currency:$coin:max";
        if (Cache::has($maxPriceKey) && Cache::get($maxPriceKey) < $price->price) {
            Cache::put($maxPriceKey, $price->price, PriceService::PRICE_CACHE_LIVE_TIME);
        }

        Cache::forget("Price:$currency:$coin:24hChange");
    }

    public function getTicker()
    {

        $result = [];
        $pairs = MasterdataService::getOneTable('coin_settings');
        foreach ($pairs as $pair) {
            if (!$pair->is_enable) {
                continue;
            }
            $currency = $pair->currency;
            $coin = $pair->coin;
            $price24h = $this->getPriceScopeIn24h($currency, $coin);
            $symbol = strtoupper("{$coin}_{$currency}");
            $result[$symbol] = [
                'base_id' => MasterdataService::getCoinId($coin),
                'quote_id' => MasterdataService::getCoinId($currency),
                'last_price' => $price24h->current_price,
                'base_volume' => $price24h->volume,
                'quote_volume' => $price24h->quote_volume,
                'isFrozen' => $pair->is_enable ? 0 : 1,
            ];
        }

        return $result;
    }

    /**
     * @return array
     */
    public function get24hPrices($req = null): array
    {

        $prices = [];
        $pricesByCoin = [];
        $currencyCoins = MasterdataService::getOneTable('coin_settings');
        foreach ($currencyCoins as $currencyCoin) {
            $currency = $currencyCoin->currency;
            $coin = $currencyCoin->coin;
            if ($currencyCoin->is_enable) {
                //if ($coin !== 'xrp' && $coin !== 'ltc') {
                if ($req && strtolower($currency) == strtolower($req)) {
                    $key = $this->getPriceKey($currency, $coin);
                    $pricesByCoin[$key] = $this->getPriceScopeIn24h($currency, $coin);
                }
                if (!$req) {
                    $key = $this->getPriceKey($currency, $coin);
                    $prices[$key] = $this->getPriceScopeIn24h($currency, $coin);
                }
                //}
            }
        }

        return $req ? $pricesByCoin : $prices;
    }

    public function getPriceScopeIn24h($currency, $coin)
    {
        $key = "Price:$currency:$coin:24hChange";

        if (Cache::has($key)) {
            return Cache::get($key);
        }

        /*$result = TmpPrice::where('currency', $currency)
            ->where('coin', $coin)
            //->where('created_at', '>=', Utils::previous24hInMillis())
            ->select(DB::raw('0 as current_price, 0 as changed_percent, max(price) as max_price, min(price) as min_price, sum(quantity) as volume, sum(amount) as quote_volume'))
            ->first();*/
        $result = TotalPrice::where('currency', $currency)
            ->where('coin', $coin)
            ->select(DB::raw('0 as current_price, 0 as changed_percent, max_price, min_price, volume, quote_volume'))
            ->first();
        if (!$result) {
            $result = (object) [
                'current_price' => 0,
                'changed_percent' => 0,
                'max_price' => 0,
                'min_price' => 0,
                'volume' => 0,
                'quote_volume' => 0
            ];
        }

        /*$fakeDataTradeSpot = env("FAKE_DATA_TRADE_SPOT", false);
        if ($fakeDataTradeSpot && isset(Consts::FAKE_CURRENCY_COINS[$coin.'_'.$currency])) {
            try {
                $client = new Client([
                    'base_uri' => Consts::DOMAIN_BINANCE_API
                ]);

                $response = $client->get('api/v3/trades', [
                    'query' => [
                        'symbol' => Consts::FAKE_CURRENCY_COINS[$coin.'_'.$currency],
                        'limit' => 30
                    ],
                    'timeout' => 5,
                    'connect_timeout' => 5,
                ]);

                $dataTrades = collect(json_decode($response->getBody()->getContents()))->sortByDesc('id');
                if (!$dataTrades->isEmpty()) {
                    $preTrade = $dataTrades->get($dataTrades->count() - 2)?? $dataTrades->get($dataTrades->count() - 1) ;
                    // get volume 24h
                    $client = new Client([
                        'base_uri' => Consts::DOMAIN_BINANCE_API
                    ]);

                    $response = $client->get('api/v3/ticker/24hr', [
                        'query' => [
                            'symbol' => Consts::FAKE_CURRENCY_COINS[$coin.'_'.$currency]
                        ],
                        'timeout' => 5,
                        'connect_timeout' => 5,
                    ]);

                    $volume = null;
                    $ticker24H = collect(json_decode($response->getBody()->getContents()));
                    $highPrice = $result->max_price;
                    if (is_null($highPrice)) { //there is no transaction today
                        $highPrice = $result->min_price ?? -1;
                    }
                    $lowPrice = $result->min_price ?? -1;
                    $quoteVolume = null;
                    if (!$ticker24H->isEmpty()) {
                        $volume = BigNumber::new($ticker24H->get('volume'))->div(10)->toString();
                        $highPrice = $highPrice >= 0 && $highPrice > $ticker24H->get('highPrice') ? $highPrice : $ticker24H->get('highPrice');
                        $lowPrice = $lowPrice >= 0 && $lowPrice < $ticker24H->get('lowPrice') ? $lowPrice : $ticker24H->get('lowPrice');
                        $quoteVolume = $ticker24H->get('quoteVolume');
                    }

                    $keyPriceMatch = 'fakePriceOrderMatch' . $currency . $coin;
                    Cache::forever($keyPriceMatch, $dataTrades->first()->price);

                    $result = (object)[
                        'current_price' => $dataTrades->first()->price,
                        'changed_percent' => BigNumber::new($dataTrades->first()->price)->sub($dataTrades->last()->price)->div($dataTrades->last()->price)->mul(100)->toString(),
                        'max_price' => $highPrice ?? $dataTrades->max('qty'),
                        'min_price' => $lowPrice ?? $dataTrades->min('qty'),
                        'volume' => $volume ?? $dataTrades->sum('qty'),
                        'quote_volume' => $quoteVolume ?? $dataTrades->sum('quoteQty'),
                        'previous_price' => $preTrade ? $preTrade->price : $dataTrades->first()->price,
                        'currency' => $currency,
                        'coin' => $coin
                    ];
                    Cache::put($key, $result, PriceService::PRICE_CACHE_LIVE_TIME);
                    return $result;
                }


            } catch (\Exception $e) {
                Log::error("getPriceScopeIn24h:fake:error");
                Log::error($e);
                throw new HttpException(400, __('data.error.getData'));
            }
        }*/

        $currentPrice = $this->getCurrentPrice($currency, $coin);
        $lastPrice = $this->getLast24hPrice($currency, $coin);
        $last24hPrice = 0;
        if ($lastPrice && $lastPrice->price != 0) { //if there is last price, there must be current price
            $last24hPrice = $lastPrice->price;
            $result->changed_percent = BigNumber::new($currentPrice->price)->sub($last24hPrice)->div($last24hPrice)->mul(100)->toString();
        }

        if ($currentPrice) {
            $result->current_price = $currentPrice->price;
            $keyPriceMatch = 'fakePriceOrderMatch' . $currency . $coin;
            Cache::forever($keyPriceMatch, $currentPrice->price);
        }

        if (is_null($result->max_price)) { //there is no transaction today
            $result->max_price = $result->min_price = $last24hPrice;
        }
        if (is_null($result->volume)) {
            $result->volume = 0;
        }

        $previousPrice = $this->getPreviousPrice($currency, $coin);
        $result->previous_price = @$previousPrice->price ?? $result->current_price;
        $result->currency = $currency;
        $result->coin = $coin;
        $result->quote_volume = $result->quote_volume ?? "0";

        Cache::put($key, $result, PriceService::PRICE_CACHE_LIVE_TIME);
        return $result;
    }

    public function getMarketInfo()
    {
        $price = 0;
        $change = 0;
        $absoluteChange = 0;
        $pairs = Price::select('coin', 'currency')
            ->groupBy('coin', 'currency')
            ->orderBy('currency', 'desc')
            ->orderBy('coin')
            ->paginate(Consts::DEFAULT_PER_PAGE);
        foreach ($pairs as $index => $pair) {
            $currency = $pair->currency;
            $coin = $pair->coin;

            $currentPrice = $this->getCurrentPrice($currency, $coin);
            $lastPrice = $this->getLast24hPrice($currency, $coin);
            $transactionAmount = $this->get24hVolumes($currency, $coin);

            if ($currentPrice && $lastPrice) {
                $absoluteChange = $currentPrice->price - $lastPrice->price;
                $price = $currentPrice->price;
                $change = $lastPrice->price != 0 ? round($absoluteChange / $lastPrice->price, 4) : 0;
            } elseif ($currentPrice) {
                $price = $currentPrice->price;
            }

            $pairs[$index]['price'] = $price;
            $pairs[$index]['absoluteChange'] = $absoluteChange;
            $pairs[$index]['change'] = $change;
            $pairs[$index]['transactionAmount'] = $transactionAmount;
        }
        return $pairs;
    }

    private function get24hVolumes($currency, $coin, $loadCache = true)
    {
        $key = "Transaction:$currency:$coin:24hVolume";
        $result = null;
        if ($loadCache && Cache::has($key)) {
            $result = Cache::get($key);
        } else {
            $result = TotalPrice::where('currency', $currency)
                ->where('coin', $coin)
                //->where('created_at', '>=', Utils::previous24hInMillis())
                //->sum('quote_volume');
                ->sum('volume');
            Cache::put($key, $result, PriceService::PRICE_CACHE_LIVE_TIME);
        }
        return $result;
    }

    public function getPricesHitory()
    {
        $key = "Prices:history";
        if (Cache::has($key)) {
            return Cache::get($key);
        }
        $coins = $this->getTop24hBtcCoins(PriceService::LIMIT_COIN_CHART);

        $result = Price::select('currency', 'coin', 'created_at', 'price')
            ->where('currency', Consts::CURRENCY_BTC)
            ->whereIn('coin', $coins)
            ->where('created_at', '>=', Utils::previousDayInMillis(3))
            ->orderBy('created_at')
            ->get();

        $result = $this->addCoinIfNeed($result);
        Cache::put($key, $result, PriceService::PRICE_CACHE_LIVE_TIME);
        return $result;
    }

    private function getCoins24h($limit = PriceService::LIMIT_COIN_CHART)
    {
        $coins = $this->getCoins()->toArray();
        $prices = TmpPrice::select('currency', 'coin', DB::raw('SUM(amount) as totalAmount'))
            ->where('currency', Consts::CURRENCY_BTC)
            //->where('created_at', '>=', Utils::previous24hInMillis())
            ->whereIn('coin', $coins)
            ->orderBy('totalAmount', 'desc')
            ->groupBy('currency', 'coin')
            ->take($limit)
            ->get();
        return $prices->pluck('coin')->toArray();
    }

    private function addCoinIfNeed($data)
    {
        $coinsExisted = $data->pluck('coin')->unique();
        $numberCoin = $coinsExisted->count();

        if ($numberCoin === PriceService::LIMIT_COIN_CHART) {
            return $data;
        }
        $newCoins = $this->getNewCoins($coinsExisted);
        return $newCoins->merge($data);
    }

    private function getTop24hBtcCoins($limit)
    {
        $coins = $this->getCoins24h($limit);
        if (!$coins || count($coins) < $limit) {
            $numberCoinExisted = $coins ? count($coins) : 0;
            $newCoins = $this->getCoins()->filter(function ($coin) use ($coins) {
                return !in_array($coin, $coins);
            });
            return array_merge($coins, $newCoins->take($limit - $numberCoinExisted)->toArray());
        }
        return $coins;
    }

    private function getNewCoins($rawData)
    {
        $numberCoin = count($rawData);
        $data = collect();

        foreach ($this->getCoins() as $coin) {
            if ($numberCoin >= PriceService::LIMIT_COIN_CHART) {
                break;
            }
            if (collect($rawData)->contains($coin)) {
                continue;
            }
            $data->push((object)['currency' => Consts::CURRENCY_BTC, 'coin' => $coin]);
            $numberCoin++;
        }

        return $data;
    }

    private function getCoins()
    {
        return collect(MasterdataService::getCoins())->filter(function ($coin) {
            return $coin !== Consts::CURRENCY_BTC;
        });
    }

    public function convertAmount($amount, $fromCurrency, $toCurrency)
    {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }
        $currentPrice = $this->getCurrentPrice($toCurrency, $fromCurrency);
        if (!empty($currentPrice)) {
            return BigNumber::new($amount)->mul($currentPrice->price)->toString();
        }

        if (Consts::CURRENCY_USD === $fromCurrency && Consts::CURRENCY_BTC === $toCurrency) {
            $usdCurrentPrice = $this->getCurrentPrice(Consts::CURRENCY_USD, Consts::CURRENCY_BTC);
            return BigNumber::new($amount)->div($usdCurrentPrice->price)->toString();
        }

        return $this->convertAmountViaBtc($amount, $fromCurrency, $toCurrency);
    }

    private function convertAmountViaBtc($amount, $sourceCurrency, $destinationCurrency)
    {
        $sourcePrice = $this->getCurrentPrice(Consts::CURRENCY_BTC, $sourceCurrency);
        $btcAmount = BigNumber::new($amount)->mul($sourcePrice->price)->toString();

        $destinationPrice = $this->getCurrentPrice(Consts::CURRENCY_BTC, $destinationCurrency);
        return BigNumber::new($btcAmount)->div($destinationPrice->price)->toString();
    }

    public function addPriceCrawled($coin, $currency, $price)
    {
        $model = new Price();
        $model->price = $price;
        $model->currency = $currency;
        $model->coin = $coin;
        $model->created_at = Carbon::now()->timestamp * 1000;
        $model->quantity = 0;
        $model->amount = 0;
        //        $model->is_crawled = Consts::TRUE;
        $model->setConnection('master');
        $model->save();
        $this->saveCurrentPriceToCache($model);

        SendPrices::dispatchIfNeed($currency, $coin);
    }

    //convert from $coin to $currency with amount is $amount
    public function convertPrice($coin, $currency, $amount, $loadCache = true, $defaultMarket = null)
    {
        $hasPair = $this->hasPairCurrencyAndCoin($currency, $coin);
        $hasPairRevert = $this->hasPairCurrencyAndCoin($coin, $currency);
        if ($hasPair) {
            $prices = $this->getCurrentPrice($currency, $coin, $loadCache);
            $result = BigNumber::new($amount)->mul($prices->price)->toString();
            return $result;
        }
        if ($hasPairRevert) {
            $prices = $this->getCurrentPrice($coin, $currency, $loadCache);
            $result = BigNumber::new($amount)->div($prices->price)->toString();
            return $result;
        }
        if ($defaultMarket && $this->hasPair($coin, $defaultMarket) && $this->hasPair($currency, $defaultMarket)) {
            return $this->convertPriceThroughDefaultMarket($coin, $currency, $defaultMarket, $amount);
        }

        $priceCurrency_BTC = $this->convertPriceToBTC($currency);
        $priceCoin_BTC = $this->convertPriceToBTC($coin);

        return BigNumber::new($priceCoin_BTC)->div($priceCurrency_BTC)->mul($amount)->toString();
    }

    public function hasPair($coin, $currency)
    {
        if ($this->hasPairCurrencyAndCoin($currency, $coin) || $this->hasPairCurrencyAndCoin($coin, $currency)) {
            return true;
        }
        return false;
    }

    //Convert from coin A to currency B through coin $defaultMarket
    public function convertPriceThroughDefaultMarket($coin, $currency, $defaultMarket, $amount)
    {
        $priceCurrency_Default = $this->convertPriceToDefault($currency, $defaultMarket);
        $priceCoin_Default = $this->convertPriceToDefault($coin, $defaultMarket);

        return BigNumber::new($priceCoin_Default)->div($priceCurrency_Default)->mul($amount)->toString();
    }

    //Convert price of any coin to $defaultMarket
    public function convertPriceToDefault($coin, $defaultMarket, $loadCache = true)
    {
        if ($coin == $defaultMarket) {
            return 1;
        }

        return $this->getCurrentPrice($defaultMarket, $coin, $loadCache)->price;
    }

    // //get price of any coin to BTC
    // public function convertPriceToBTC($coin, $loadCache = true)
    // {
    //     if($coin == Consts::CURRENCY_BTC) return 1;

    //     $priceETH_BTC = $this->getCurrentPrice(Consts::CURRENCY_BTC, Consts::CURRENCY_ETH, $loadCache)->price;
    //     $priceUSDT_BTC = $this->getCurrentPrice(Consts::CURRENCY_BTC, Consts::CURRENCY_USDT, $loadCache)->price;
    //     $priceUSD_BTC = $this->getCurrentPrice(Consts::CURRENCY_BTC, Consts::CURRENCY_USD, $loadCache)->price;
    //     if($this->hasPairCurrencyAndCoin(Consts::CURRENCY_BTC, $coin) || $this->hasPairCurrencyAndCoin($coin, Consts::CURRENCY_BTC)) {
    //         return $this->getCurrentPrice(Consts::CURRENCY_BTC, $coin, $loadCache)->price;
    //     }

    //     if($this->hasPairCurrencyAndCoin(Consts::CURRENCY_ETH, $coin) || $this->hasPairCurrencyAndCoin($coin, Consts::CURRENCY_ETH)) {
    //         $priceWithETH = $this->getCurrentPrice(Consts::CURRENCY_ETH, $coin, $loadCache)->price;
    //         return BigNumber::new($priceWithETH)->mul($priceETH_BTC)->toString();
    //     }

    //     if($this->hasPairCurrencyAndCoin(Consts::CURRENCY_USDT, $coin) || $this->hasPairCurrencyAndCoin($coin, Consts::CURRENCY_USDT)) {
    //         $priceWithUSDT = $this->getCurrentPrice(Consts::CURRENCY_USDT, $coin, $loadCache)->price;
    //         return BigNumber::new($priceWithUSDT)->mul($priceUSDT_BTC)->toString();
    //     }

    //     if($this->hasPairCurrencyAndCoin(Consts::CURRENCY_USD, $coin) || $this->hasPairCurrencyAndCoin($coin, Consts::CURRENCY_USD)) {
    //         $priceWithUSD = $this->getCurrentPrice(Consts::CURRENCY_USD, $coin, $loadCache)->price;
    //         return BigNumber::new($priceWithUSD)->mul($priceUSD_BTC)->toString();
    //     }

    //     return '0';

    // }

    public function convertBTCtoAMALMargin()
    {
        $enableBTC_USDT = CoinSetting::where('currency', Consts::CURRENCY_USDT)->where('coin',
            Consts::CURRENCY_BTC)->first();
        $enableAMAL_USDT = CoinSetting::where('currency', Consts::CURRENCY_USDT)->where('coin',
            Consts::CURRENCY_AMAL)->first();
        if (!$enableAMAL_USDT || !$enableBTC_USDT) {
            return 0;
        }
        $priceUSDT_BTC = $this->getCurrentPrice24h(Consts::CURRENCY_USDT, Consts::CURRENCY_BTC, false)->price;
        $priceUSDT_AMAL = $this->getCurrentPrice24h(Consts::CURRENCY_USDT, Consts::CURRENCY_AMAL, false)->price;
        if ($priceUSDT_AMAL == 0) {
            return 0;
        }
        return BigNumber::new($priceUSDT_BTC)->div($priceUSDT_AMAL)->toString();
    }

    //get price of any coin to BTC
    public function convertPriceToBTC($coin, $loadCache = false)
    {
        if ($coin == Consts::CURRENCY_BTC) {
            return 1;
        }

        $priceETH_BTC = $this->getCurrentPrice24h(Consts::CURRENCY_BTC, Consts::CURRENCY_ETH, $loadCache)->price;
        $priceUSDT_BTC = $this->getCurrentPrice24h(Consts::CURRENCY_USDT, Consts::CURRENCY_BTC, $loadCache)->price;
        $priceUSD_BTC = $this->getCurrentPrice24h(Consts::CURRENCY_USD, Consts::CURRENCY_BTC, $loadCache)->price;

        $statusCoinPerBTC = CoinSetting::where('currency', Consts::CURRENCY_BTC)->where('coin', $coin)->first();
        $enablePriceCoin = @$statusCoinPerBTC->is_enable ?? 0;

        $statusCoinPerETH = CoinSetting::where('currency', Consts::CURRENCY_ETH)->where('coin', $coin)->first();
        $enablePriceCoinETH = @$statusCoinPerETH->is_enable ?? 0;

        $statusCoinPerUSDT = CoinSetting::where('currency', Consts::CURRENCY_USDT)->where('coin', $coin)->first();
        $enablePriceCoinUSDT = @$statusCoinPerUSDT->is_enable ?? 0;

        $statusCoinPerUSD = CoinSetting::where('currency', Consts::CURRENCY_USD)->where('coin', $coin)->first();
        $enablePriceCoinUSD = @$statusCoinPerUSD->is_enable ?? 0;

        $enablePriceETHPerBTC = CoinSetting::where('currency', Consts::CURRENCY_BTC)->where('coin',
            Consts::CURRENCY_ETH)->first();
        $enablePriceETHBTC = @$enablePriceETHPerBTC->is_enable ?? 0;

        //USDT / BTC is not available so we're trading off for otherwise case ( BTC / USDT)
        $statusUSDTPerBTC = CoinSetting::where('currency', Consts::CURRENCY_USDT)->where('coin',
            Consts::CURRENCY_BTC)->first();
        $enablePriceUSDTBTC = @$statusUSDTPerBTC->is_enable ?? 0;

        //USD / BTC is not available so we're trading off for otherwise case ( BTC / USD)
        $statusUSDPerBTC = CoinSetting::where('currency', Consts::CURRENCY_USD)->where('coin',
            Consts::CURRENCY_BTC)->first();
        $enablePriceUSDBTC = @$statusUSDPerBTC->is_enable ?? 0;

        //TODO: In case we have another market name "$coin" (eg.AMAL), double check if the pair BTC/$coin available
        //Because we dont have one, so the snippet code below only check the necessary:
        // Coin/BTC, (BTC/Coin - Skip), Coin/USDT, (USDT/Coin - Skip), USD/BTC, (BTC/USDT), Coin/USD, (USD/Coin - Skip), USD/BTC, (BTC/USD),

        if (($enablePriceCoin) && ($this->hasPairCurrencyAndCoin(Consts::CURRENCY_BTC,
                    $coin) || $this->hasPairCurrencyAndCoin($coin, Consts::CURRENCY_BTC))) {
            $price24h = $this->getCurrentPrice24h(Consts::CURRENCY_BTC, $coin, $loadCache)->price;
            if ($price24h !== 0) {
                return $price24h;
            }
        }

        if ($enablePriceCoinUSDT && ($this->hasPairCurrencyAndCoin(Consts::CURRENCY_USDT,
                    $coin) || $this->hasPairCurrencyAndCoin($coin, Consts::CURRENCY_USDT))) {
            $priceWithUSDT = $this->getCurrentPrice24h(Consts::CURRENCY_USDT, $coin, $loadCache)->price;
            if ($priceWithUSDT !== 0 && $enablePriceUSDTBTC && $priceUSDT_BTC !== 0) {
                return BigNumber::new($priceWithUSDT)->div($priceUSDT_BTC)->toString();
            }
        }

        if ($enablePriceCoinETH && ($this->hasPairCurrencyAndCoin(Consts::CURRENCY_ETH,
                    $coin) || $this->hasPairCurrencyAndCoin($coin, Consts::CURRENCY_ETH))) {
            $priceWithETH = $this->getCurrentPrice24h(Consts::CURRENCY_ETH, $coin, $loadCache)->price;
            if ($priceWithETH !== 0 && $enablePriceETHBTC && $priceETH_BTC !== 0) {
                return BigNumber::new($priceWithETH)->mul($priceETH_BTC)->toString();
            }
        }

        if ($enablePriceCoinUSD && ($this->hasPairCurrencyAndCoin(Consts::CURRENCY_USD,
                    $coin) || $this->hasPairCurrencyAndCoin($coin, Consts::CURRENCY_USD))) {
            $priceWithUSD = $this->getCurrentPrice24h(Consts::CURRENCY_USD, $coin, $loadCache)->price;
            if ($priceWithUSD !== 0 && $enablePriceUSDBTC && $priceUSD_BTC !== 0) {
                return BigNumber::new($priceWithUSD)->div($priceUSD_BTC)->toString();
            }
        }

        //TODO add more market if needed

        return '0';
    }

    public function sort24h($collection, $sort, $sort_type)
    {
        if ($sort == 'pair') {
            if ($sort_type == Consts::SORT_ASC) {
                return $collection->sortBy(function ($item) {
                    return $item->coin;
                })->toArray();
            }
            if ($sort_type == Consts::SORT_DESC) {
                return $collection->sortByDesc(function ($item) {
                    return $item->coin;
                })->toArray();
            }
        }
        if ($sort == 'current_price') {
            if ($sort_type == Consts::SORT_ASC) {
                return $collection->sortBy(function ($item) {
                    return $item->current_price;
                })->toArray();
            }
            if ($sort_type == Consts::SORT_DESC) {
                return $collection->sortByDesc(function ($item) {
                    return $item->current_price;
                })->toArray();
            }
        }
        if ($sort == 'changed_percent') {
            if ($sort_type == Consts::SORT_ASC) {
                return $collection->sortBy(function ($item) {
                    return $item->changed_percent;
                })->toArray();
            }
            if ($sort_type == Consts::SORT_DESC) {
                return $collection->sortByDesc(function ($item) {
                    return $item->changed_percent;
                })->toArray();
            }
        }
        if ($sort == 'max_price') {
            if ($sort_type == Consts::SORT_ASC) {
                return $collection->sortBy(function ($item) {
                    return $item->max_price;
                })->toArray();
            }
            if ($sort_type == Consts::SORT_DESC) {
                return $collection->sortByDesc(function ($item) {
                    return $item->max_price;
                })->toArray();
            }
        }
        if ($sort == 'min_price') {
            if ($sort_type == Consts::SORT_ASC) {
                return $collection->sortBy(function ($item) {
                    return $item->min_price;
                })->toArray();
            }
            if ($sort_type == Consts::SORT_DESC) {
                return $collection->sortByDesc(function ($item) {
                    return $item->min_price;
                })->toArray();
            }
        }
        if ($sort == 'volume') {
            if ($sort_type == Consts::SORT_ASC) {
                return $collection->sortBy(function ($item) {
                    return $item->volume;
                })->toArray();
            }
            if ($sort_type == Consts::SORT_DESC) {
                return $collection->sortByDesc(function ($item) {
                    return $item->volume;
                })->toArray();
            }
        }
        if ($sort == 'quote_volume') {
            if ($sort_type == Consts::SORT_ASC) {
                return $collection->sortBy(function ($item) {
                    return $item->quote_volume;
                })->toArray();
            }
            if ($sort_type == Consts::SORT_DESC) {
                return $collection->sortByDesc(function ($item) {
                    return $item->quote_volume;
                })->toArray();
            }
        }
    }
}
