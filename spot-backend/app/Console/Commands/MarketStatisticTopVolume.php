<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\MarketStatisticService;
use App\Http\Services\PriceService;
use App\Models\Coin;
use App\Models\CoinSetting;
use App\Models\MarketStatistic;
use App\Models\MarketTopVolume;
use App\Models\Price;
use App\Models\TmpPrice;
use App\Models\TotalPrice;
use App\Utils;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MarketStatisticTopVolume extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'market:top_volume';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update top volume coin every day';

    private PriceService $priceService;

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
        $coinIn = $this->getListCoin();
        $yesterday = Utils::yesterdaySub5MinuteInMillis();
        // $yesterday =  [
        //     Carbon::now()->subHours(1)->timestamp * 1000
        // ];
        $listVolume = [];

        foreach ($coinIn as $coin) {
            if (!$this->checkPairUSDT($coin)) {
                continue;
            }
//            $result = TmpPrice::where('coin', $coin)
//                ->where('created_at', '>=', current($yesterday))
//                ->select(DB::raw('0 as changed_percent, sum(quantity) as volume, coin'))
//                ->first();

            $result = TotalPrice::where('currency', Consts::CURRENCY_USDT)
                ->where('coin', $coin)
                ->select(DB::raw('0 as current_price, 0 as changed_percent, volume, coin'))
                ->first();

            $currentPrice = $this->priceService->getCurrentPrice('usdt', $coin);
            $lastPrice = $this->priceService->getLast24hPrice('usdt', $coin);
            $last24hPrice = 0;

            if ($lastPrice && $lastPrice->price !== 0) { //if there is last price, there must be current price
                $last24hPrice = $lastPrice->price;
                $result->changed_percent = BigNumber::new($currentPrice->price)->sub($last24hPrice)->div($last24hPrice)->mul(100)->toString();
                $previousPrice = $this->priceService->getPreviousPrice('usdt', $coin);
                $result->previous_price = @$previousPrice->price ?? $result->current_price;
            }

            if ($result->coin && $result->coin != 'usdt') {
                $listVolume[] = $result;
            }
        }

        if (count($listVolume) > 0) {
            usort($listVolume, function ($firstItem, $secondItem) {
                return $firstItem->volume < $secondItem->volume;
            });

            $this->saveStatistic($listVolume);
        } else {
            $this->getLastestVolume();
        }

        return Command::SUCCESS;
    }

    private function checkPairUSDT($coin): bool
    {
        $checkPairUSDT = CoinSetting::query()->where([
            ['coin', $coin],
            ['currency', Consts::CURRENCY_USDT],
//            ['is_enable', true],
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

        $listCoin = Coin::select('coin')
        ->whereNotIn('coin', ['xrp', 'ltc'])
        ->get();
        foreach ($listCoin as $coin) {
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
                    'top' => $key + 1,
                    'quantity' => $param->volume,
                    'lastest_price' => $param->previous_price,
                    'changed_percent' => $param->changed_percent,
                    'created_at' => Carbon::now()->timestamp * 1000
                ];
                $data[] = $dataParam;
            }
        }

        return MarketTopVolume::insert($data);
    }

    private function getLastestVolume()
    {
        $data = [];
        $topVolume = MarketTopVolume::query()
            ->whereIn('top', [1, 2, 3])
            ->orderBy('created_at', 'desc')
            ->limit(Consts::LIMIT_TOP_MARKET)
            ->get();

        foreach ($topVolume as $volume) {
            $dataParam = [
                'name' => $volume->name,
                'top'  => $volume->top,
                'quantity' => $volume->volume,
                'lastest_price' => $volume->previous_price,
                'changed_percent' => $volume->changed_percent,
                'created_at' => Carbon::now()->timestamp * 1000
            ];
            $data[] = $dataParam;
        }

        return MarketTopVolume::query()
            ->insert($data);
    }
}
