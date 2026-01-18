<?php

namespace App\Jobs;

use App\Events\CoinMarketCapTickerUpdated;
use App\Models\CoinMarketCapTicker;
use App\Models\Price;
use App\Consts;
use Carbon\Carbon;
use App\Http\Services\MasterdataService;
use App\Http\Services\PriceService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use Exception;

class CrawlCoinMarketCapTicker implements ShouldQueue
{
    const CMC_CACHE_LIVE_TIME = 120; // 2 minutes
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $coinSymbols = [
        'BTC', 'ETH', 'XRP', 'BCH', 'LTC', 'DASH', 'NEM', 'BCC',
        'NEO', 'XMR', 'MIOTA', 'ETC', 'QTUM', 'ADA', 'XLM', 'LSK', 'ZEC',
        'HSR', 'WAVES', 'STRAT', 'BCN', 'ARK', 'STEEM', 'PIVX', 'EOS', 'BTG',
    ];

    protected $currency;

    /**
     * Create a new job instance.
     * @internal param Client $client
     */
    public function __construct($currency = null)
    {
        $this->currency = $currency;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (empty($this->currency)) {
            self::crawlPriceByCountryCurreny();

            CrawlCoinMarketCapTicker::dispatch(Consts::CURRENCY_USD)->onQueue(Consts::QUEUE_CRAWLER);
        } else {
            self::crawlUsdPrice();
        }
    }

    /*
     * Crawl coin price by currency of country which is setting in database.
     */
    private function crawlPriceByCountryCurreny()
    {
        try {
            $currency = self::getCurrency();
            $data = self::getTickerByCurrency($currency);
            $result = self::processResponse($data, $currency);

            $result = collect($result);
            $key = Consts::CACHE_KEY_CMC_TICKER_CURRENCY . $currency;
            Cache::put($key, $result, CrawlCoinMarketCapTicker::CMC_CACHE_LIVE_TIME);
            event(new CoinMarketCapTickerUpdated($result));
        } catch (Exception $e) {
            logger()->error($e->getMessage());
        }
    }

    /*
     * Crawl coin_usd price
     */
    private function crawlUsdPrice()
    {
        $priceService = new PriceService();
        $coins = MasterdataService::getCurrenciesAndCoins();

        try {
            $rows = self::getTickerByCurrency($this->currency);
            foreach ($rows as $row) {
                $coin = strtolower($row['symbol']);
                if (!in_array($coin, $coins)) {
                    continue;
                }
                $priceService->addPriceCrawled($coin, Consts::CURRENCY_USD, $row['price_usd']);
            }
        } catch (Exception $e) {
            logger()->error($e->getMessage());
        }
    }

    private function processResponse($rawData, $currency)
    {
        $result = [];
        foreach ($rawData as $row) {
            if (!in_array($row['symbol'], $this->coinSymbols)) {
                continue;
            }
            $coinMarketCapTicker                            = new CoinMarketCapTicker();
            $coinMarketCapTicker->name                      = $row['name'];
            $coinMarketCapTicker->symbol                    = $row['symbol'];
            $coinMarketCapTicker->rank                      = $row['rank'];
            $coinMarketCapTicker->price_usd                 = $row['price_usd'];
            $coinMarketCapTicker->price_btc                 = $row['price_btc'];
            $coinMarketCapTicker->{'24h_volume_usd'}        = $row['24h_volume_usd'];
            $coinMarketCapTicker->market_cap_usd            = $row['market_cap_usd'];
            $coinMarketCapTicker->available_supply          = $row['available_supply'];
            $coinMarketCapTicker->total_supply              = $row['total_supply'];
            $coinMarketCapTicker->max_supply                = $row['max_supply'];
            $coinMarketCapTicker->percent_change_1h         = $row['percent_change_1h'];
            $coinMarketCapTicker->percent_change_24h        = $row['percent_change_24h'];
            $coinMarketCapTicker->percent_change_7d         = $row['percent_change_7d'];
            $coinMarketCapTicker->last_updated              = Carbon::createFromTimestamp($row['last_updated']);
            $coinMarketCapTicker->price_usd                 = $row['price_usd'];
            $coinMarketCapTicker->{'24h_volume_usd'}        = $row['24h_volume_usd'];
            $coinMarketCapTicker->market_cap_usd            = $row['market_cap_usd'];

            $coinMarketCapTicker->{'price_currency'}        = $row["price_{$currency}"] ?? '';
            $coinMarketCapTicker->{'24h_volume_currency'}   = $row["24h_volume_{$currency}"] ?? '';
            $coinMarketCapTicker->{'market_cap_currency'}   = $row["market_cap_{$currency}"] ?? '';

            $result[] = $coinMarketCapTicker;
        }
        return $result;
    }

    private function getCurrency()
    {
        $setting = MasterdataService::getOneTable('settings')
            ->filter(function ($value, $key) {
                return $value->key === Consts::SETTING_CURRENCY_COUNTRY;
            })
            ->first();
        return $setting ? $setting->value : Consts::CURRENCY_USD;
    }

    private function getTickerByCurrency($currency)
    {
        $client = new Client([
            'base_uri' => 'https://api.coinmarketcap.com']);
        $response = $client->get("v1/ticker/?convert={$currency}");
        return json_decode($response->getBody(), true);
    }
}
