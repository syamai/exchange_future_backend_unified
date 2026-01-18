<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\PriceService;
use App\Models\CoinsConfirmation;
use App\Models\CoinSetting;
use App\Models\MarketStatistic;
use App\Models\MarketTopListing;
use App\Models\Price;
use App\Models\TmpPrice;
use App\Models\TotalPrice;
use App\Utils;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarketStatisticTopListing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'market:top_listing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update top listing coin every day';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(PriceService $priceService)
    {
        parent::__construct();
        $this->priceService = $priceService;
    }


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $listListing = [];
        $coinIns = $this->getListCoin();
        $yesterday = Utils::yesterdaySub5MinuteInMillis();
        // $yesterday =  [
        //     Carbon::now()->subHours(1)->timestamp * 1000,
        //     Carbon::now()->timestamp * 1000,
        // ];
        foreach ($coinIns as $value) {
            if (!$this->checkPairUSDT($value)) {
                continue;
            }

//            $result = TmpPrice::where('coin', $value)
//                ->where('created_at', '>=', current($yesterday))
//                ->where('created_at', '<=', end($yesterday))
//                ->select(DB::raw('0 as changed_percent, sum(quantity) as volume, coin'))
//                ->first();

            $result = TotalPrice::where('currency', Consts::CURRENCY_USDT)
                ->where('coin', $value)
                ->select(DB::raw('0 as current_price, 0 as changed_percent, volume, coin'))
                ->first();

            $currentPrice = $this->priceService->getCurrentPrice('usdt', $value);
            $lastPrice = $this->priceService->getLast24hPrice('usdt', $value);
            $last24hPrice = 0;

            if ($lastPrice && $lastPrice->price !== 0) { //if there is last price, there must be current price
                $last24hPrice = $lastPrice->price;
                $result->changed_percent = BigNumber::new($currentPrice->price)->sub($last24hPrice)->div($last24hPrice)->mul(100)->toString();
                $previousPrice = $this->priceService->getPreviousPrice('usdt', $value);
                $result->previous_price = @$previousPrice->price ?? $result->current_price;
            }

            if (!isset($result->coin)) {
                $result->coin = $value;
            }

            $listListing[] = $result;
        }

        if (count($listListing) > 0) {
            $this->saveStatistic($listListing);
        }

        return Command::SUCCESS;
    }

    private function checkPairUSDT($coin): bool
    {
        $checkPairUSDT = CoinSetting::query()->where([
            ['coin', $coin],
            ['currency', Consts::CURRENCY_USDT],
            ['is_enable', true],
            ['zone', Consts::ZONE_DEFAULT]
        ])->first();

        if (!$checkPairUSDT) {
            return false;
        }

        return true;
    }

    private function getListCoin()
    {
        $coinIn = [];

        $listCoins = CoinsConfirmation::select('coin')
            ->whereNotIn('coin', ['xrp', 'ltc'])
            ->orderBy('id', 'desc')
            ->get();
        foreach ($listCoins as $coin) {
            $coinIn[] = $coin->coin;
        }
        return $coinIn;
    }

    private function saveStatistic($params)
    {
        $data = [];

        foreach ($params as $key => $param) {
            if ($key <= 2) {                    // only save top 3
                $dataParam = [
                    'name' => $param->coin,
                    'top'  => $key + 1,
                    'quantity' => $param->volume,
                    'lastest_price' => $param->previous_price,
                    'changed_percent' => $param->changed_percent,
                    'created_at' => Carbon::now()->timestamp * 1000
                ];
                $data[] = $dataParam;
            }
        }

        return MarketTopListing::query()
            ->insert($data);
    }
}
