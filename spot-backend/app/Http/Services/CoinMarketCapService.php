<?php
namespace App\Http\Services;

use App\Consts;
use App\Events\AmlSettingUpdated;
use App\Events\CoinCheckPriceAmlUpdated;
use App\Events\CoinMarketCapTickerUpdated;
use App\Models\AmalSetting;
use App\Models\CoinMarketCapTicker;
use App\Models\Price;
use App\Utils\BigNumber;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class CoinMarketCapService
{
    public function getCurrentCurrenciesRate(): array
    {
        $this->updateDataBase();
        $coin = DB::table('coin_market_cap_tickers')->select('symbol', 'price_usd')->whereIn('name', ['Bitcoin', 'Ethereum'])->get();
        $aml = DB::table('amal_settings')->value('usd_price');
        $price_aml = array('AML' => $aml);
        $listPriceCurrencies = array();
        foreach ($coin->toArray() as $tempCoin) {
            if ($tempCoin->symbol === 'BTC') {
                $newArr = array('BTC' => (string)($tempCoin->price_usd));
                $listPriceCurrencies = array_merge($listPriceCurrencies, $newArr);
            } elseif ($tempCoin->symbol === 'ETH') {
                $newArr = array('ETH' => (string)($tempCoin->price_usd));
                $listPriceCurrencies = array_merge($listPriceCurrencies, $newArr);
            }
        }
        $listPriceCurrencies = array_merge($listPriceCurrencies, $price_aml);
        return $listPriceCurrencies;
    }

    public function updateDataBase(): void
    {
        $client = new Client([
        'base_uri' => 'https://pro-api.coinmarketcap.com']);
        $key = config('blockchain.key_coin_marketcap');
        $response = $client->get('/v1/cryptocurrency/listings/latest', [
        'headers' => [
            'X-CMC_PRO_API_KEY' => $key
        ]
        ]);

        $ethPrice = null;
        $btcPrice = null;
        $usdtPrice = null;
        $data = json_decode($response->getBody(), true);
        $resultArr = array();
        $tempData = Arr::get($data, 'data');
        foreach ($tempData as $arr) {
            if (Arr::get($arr, 'name') === 'Bitcoin' || Arr::get($arr, 'name') === 'Ethereum' || Arr::get($arr, 'name') === 'Tether') {
                $price = Arr::get($arr, 'quote');
                $usd = Arr::get($price, 'USD');
                $coin = CoinMarketCapTicker::firstOrNew(['name' => Arr::get($arr, 'name')]);
                $coin->fill([
                'name' => Arr::get($arr, 'name'),
                'symbol' => Arr::get($arr, 'symbol'),
                'rank' => Arr::get($arr, 'cmc_rank'),
                'price_usd' => Arr::get($usd, 'price'),
                'price_btc' => $coin->price_btc,
                '24h_volume_usd' => Arr::get($usd, 'volume_24h'),
                'market_cap_usd' => Arr::get($usd, 'market_cap'),
                'available_supply' => Arr::get($arr, 'circulating_supply'),
                'total_supply' => Arr::get($arr, 'total_supply'),
                'max_supply' => Arr::get($arr, 'max_supply'),
                'percent_change_1h' => Arr::get($usd, 'percent_change_1h'),
                'percent_change_24h' => Arr::get($usd, 'percent_change_24h'),
                'percent_change_7d' => Arr::get($usd, 'percent_change_7d'),
                'last_updated' => (date('Y-m-d H:i:s', strtotime(Arr::get($usd, 'last_updated')))),
                ]);
                if (Arr::get($arr, 'name') === 'Ethereum') {
                      $btccoin = CoinMarketCapTicker::firstOrNew(['name' => 'Bitcoin']);
                      $ethPrice = Arr::get($usd, 'price');
                    if (Arr::get($btccoin, 'price_usd') != null) {
                        $coin->price_btc = (new BigNumber($ethPrice))->div($btccoin->price_usd)->toString();
                    }
                }
                if (Arr::get($arr, 'name') === 'Tether') {
                    $btccoin = CoinMarketCapTicker::firstOrNew(['name' => 'Bitcoin']);
                    $usdtPrice = Arr::get($usd, 'price');
                    if (Arr::get($btccoin, 'price_usd') != null) {
                        $coin->price_btc = (new BigNumber($usdtPrice))->div($btccoin->price_usd)->toString();
                    }
                }
                if (Arr::get($arr, 'name') === 'Bitcoin') {
                    $btcPrice = Arr::get($usd, 'price');
                }
                $coin->save();
                array_push($resultArr, $coin);
            }
        }
        if ($ethPrice !== null && $btcPrice !== null && $usdtPrice !== null) {
            $this->updateAMLSetting($ethPrice, $btcPrice, $usdtPrice);
        }
        event(new CoinMarketCapTickerUpdated($resultArr));
    }

    private function updateAMLSetting($ethPrice, $btcPrice, $usdtPrice): void
    {
        $aml = AmalSetting::first();
        $aml->eth_price = $this->getRound(BigNumber::new($aml->usd_price)->div($ethPrice)->toString());
        $aml->btc_price = $this->getRound(BigNumber::new($aml->usd_price)->div($btcPrice)->toString());
        $aml->usdt_price = $this->getRound(BigNumber::new($aml->usd_price)->div($usdtPrice)->toString());
        $aml->save();
        event(new CoinCheckPriceAmlUpdated($aml->toArray()));
        event(new AmlSettingUpdated($aml));
    }

    protected function getRound($input): string
    {
        return BigNumber::round($input, BigNumber::ROUND_MODE_FLOOR, Consts::DIGITS_NUMBER_PRECISION);
    }

    protected function callRequestCoinMarketCap($convertByCurrency)
    {
        $client = new Client(
            [
                'base_uri' => 'https://pro-api.coinmarketcap.com'
            ]
        );
        $key = config('blockchain.key_coin_marketcap');
        $response = $client->get('/v1/cryptocurrency/listings/latest?convert='.$convertByCurrency, [
            'headers' => [
                'X-CMC_PRO_API_KEY' => $key,
            ]
        ]);

        $data = json_decode($response->getBody(), true);
        $data = Arr::get($data, 'data');

        return $data;
    }

    protected function convertMarketPrices($prices, $targetMarket): array
    {
        $targetPrice = @$prices[$targetMarket] ?? 1;
        $marketPrices = [];
        foreach ($prices as $coin => $coinPrice) {
            $marketPrices[$coin] = BigNumber::new($coinPrice)->div($targetPrice)->toString();
        }

        return $marketPrices;
    }

    protected function getLastPriceRecord()
    {
        return Price::orderBy('id', 'desc')->first();
    }

    protected function removeBlackListCoinPair($lastPriceRecordId): void
    {
        $priceRecords = Price::where('id', '>', $lastPriceRecordId)
                            ->where('amount', 0)
                            ->where('quantity', 0)
                            ->get();
        $blockPairs = [
            // BTC
            ['BCH', 'BTC'],
            ['ETH', 'BTC'],

            // ETH
            ['BCH', 'ETH'],
            ['LTC', 'ETH'],

            // USDT
            ['BCH', 'USDT'],
            ['BTC', 'USDT'],
            ['ETH', 'USDT'],
        ];

        foreach ($priceRecords as $priceRecord) {
            foreach ($blockPairs as $blockPair) {
                $coin = strtoupper($priceRecord->coin);
                $currency = strtoupper($priceRecord->currency);
                if (($coin == $blockPair[0] && $currency == $blockPair[1]) ||
                    ($coin == $blockPair[1] && $currency == $blockPair[0])
                ) {
                    $priceRecord->delete();
                }
            }
        }
    }

    public function crawlPrice(): void
    {
        $lastPriceRecord = $this->getLastPriceRecord();
        $lastPriceRecordId = @$lastPriceRecord->id ?? 0;

        // Insert Market USDT
        $convertByCurrency = 'USDT';
        $currency = strtoupper($convertByCurrency);
        $result = $this->crawlPriceFromCoinMarketCap($currency);
        $this->insertCoinsToPrices($result, $currency);

        // Insert Market ETH, BTC
        $markets = ['ETH', 'BTC'];
        foreach ($markets as $market) {
            $resultMarket = $this->convertMarketPrices($result, $market);
            $this->insertCoinsToPrices($resultMarket, $market);
        }

        // Crawl coin price by USD
        $currency = strtoupper(Consts::CURRENCY_USD);
        $resultUsd = $this->crawlPriceFromCoinMarketCap($currency);
        $this->insertCoinsToPrices($resultUsd, 'USD');

        // Insert AMAL coin Pair
        $res = $this->insertAmalToPrices($resultUsd);

        // Remove coin pair in Black List
        $this->removeBlackListCoinPair($lastPriceRecordId);

        Artisan::call('cache:clear');
    }

    protected function insertAmalToPrices($resultUsd): bool
    {
//        Sample data $resultUsd:
//        array:8 [
//          "BTC" => 8113.96197808
//          "ETH" => 177.447992216
//          "XRP" => 0.300120580334
//          "USDT" => 1.0047449898
//          "BCH" => 220.134373605
//          "LTC" => 54.5195733054
//          "EOS" => 2.95274312749
//          "ADA" => 0.0394018711559
//        ]

        $aml = AmalSetting::first();
        $usdPrice = $aml->usd_price;
        $marketPrice = @$resultUsd['BTC'] ?? $aml->btc_price;
        $res = $this->getRound(BigNumber::new($usdPrice)->div($marketPrice)->toString());
        Price::insert([
            'currency' => Consts::CURRENCY_BTC,
            'coin' => Consts::CURRENCY_AMAL,
            'price' => $res,
            'quantity' => 0,
            'amount' => 0,
            'created_at' => now()->timestamp * 1000,
        ]);

        $marketPrice = @$resultUsd['ETH'] ?? $aml->eth_price;
        $res = $this->getRound(BigNumber::new($usdPrice)->div($marketPrice)->toString());
        Price::insert([
            'currency' => Consts::CURRENCY_ETH,
            'coin' => Consts::CURRENCY_AMAL,
            'price' => $res,
            'quantity' => 0,
            'amount' => 0,
            'created_at' => now()->timestamp * 1000,
        ]);

        $marketPrice = @$resultUsd['USDT'] ?? $aml->usdt_price;
        $res = $this->getRound(BigNumber::new($usdPrice)->div($marketPrice)->toString());
        Price::insert([
            'currency' => Consts::CURRENCY_USDT,
            'coin' => Consts::CURRENCY_AMAL,
            'price' => $res,
            'quantity' => 0,
            'amount' => 0,
            'created_at' => now()->timestamp * 1000,
        ]);

        $marketPrice = $aml->usd_price;
        Price::insert([
            'currency' => Consts::CURRENCY_USD,
            'coin' => Consts::CURRENCY_AMAL,
            'price' => $marketPrice,
            'quantity' => 0,
            'amount' => 0,
            'created_at' => now()->timestamp * 1000,
        ]);

        return true;
    }

    protected function insertCoinsToPrices($result, $currency): void
    {
        // Insert Data to prices table
        foreach ($result as $key => $res) {
            Price::insert([
                'currency' => strtolower($currency),
                'coin' => strtolower($key),
                'price' => $res,
                'quantity' => 0,
                'amount' => 0,
                'created_at' => now()->timestamp * 1000,
            ]);
        }
    }

    public function crawlPriceFromCoinMarketCap($convertByCurrency = Consts::CURRENCY_BTC): array
    {
        $data = $this->callRequestCoinMarketCap(strtoupper($convertByCurrency));
        $coins = MasterdataService::getOneTable('coins')->pluck('coin', 'id')->toArray();

        $currency = strtoupper($convertByCurrency);    // BTC
        $result = [];
        foreach ($data as $item) {
            $symbol = Arr::get($item, 'symbol');   // BTC
            if ($symbol == $currency) {
                continue;
            }
            if (!in_array(strtolower($symbol), $coins)) {
                continue;
            }

            $result[$symbol] = $this->extractDataItem($item, $currency);
        }

        return $result;
    }

    protected function extractDataItem($item, $convertByCurrency)
    {
//        Sample data:
//        array:14 [
//        "id" => 1
//          "name" => "Bitcoin"
//          "symbol" => "BTC"
//          "slug" => "bitcoin"
//          "num_market_pairs" => 7696
//          "date_added" => "2013-04-28T00:00:00.000Z"
//          "tags" => array:1 [
//              0 => "mineable"
//          ]
//          "max_supply" => 21000000
//          "circulating_supply" => 17996925
//          "total_supply" => 17996925
//          "platform" => null
//          "cmc_rank" => 1
//          "last_updated" => "2019-10-17T10:17:34.000Z"
//          "quote" => array:1 [
//              "BTC" => array:7 [
//                  "price" => 1
//                  "volume_24h" => 1980139.2532023
//                  "percent_change_1h" => 0
//                  "percent_change_24h" => 0
//                  "percent_change_7d" => 0
//                  "market_cap" => 17996925
//                  "last_updated" => "2019-10-17T10:17:34.000Z"
//              ]
//          ]
//        ]

        $price = Arr::get($item, 'quote.'.$convertByCurrency.'.price');

        return $price ?? 0;
    }
}
