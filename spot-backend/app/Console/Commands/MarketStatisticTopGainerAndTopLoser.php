<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\PriceService;
use App\Models\Coin;
use App\Models\CoinSetting;
use App\Models\MarketGainer;
use App\Models\MarketLoser;
use App\Models\MarketStatistic;
use App\Models\Price;
use App\Models\TmpPrice;
use App\Models\TotalPrice;
use App\Utils;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarketStatisticTopGainerAndTopLoser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'market:top_gainer_loser';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update top gainer and top loser coin every day';

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
        $listCoinsGainer = [];
        $listCoinsLoser = [];
        $coinIns = $this->getListCoin();
        $yesterday = Utils::yesterdaySub5MinuteInMillis();
        // $yesterday = [Carbon::now()->subHour()->timestamp * 1000];

        foreach ($coinIns as $value) {
            if (!$this->checkPairUSDT($value)) {
                continue;
            }
//            $result = TmpPrice::where('coin', $value)
//                ->where('created_at', '>=', current($yesterday))
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

            if (isset($result->coin) && (int)$result->changed_percent > 0) {
                $listCoinsGainer[] = $result;
            }

            if (isset($result->coin) && (int)$result->changed_percent < 0) {
                $listCoinsLoser[] = $result;
            }
        }

        $this->topGainer($listCoinsGainer);
        $this->topLoser($listCoinsLoser);

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

        $listCoins = Coin::select('coin')
        ->whereNotIn('coin', ['xrp', 'ltc'])
        ->get();
        foreach ($listCoins as $coin) {
            $coinIn[] = $coin->coin;
        }
        return $coinIn;
    }

    private function saveStatistic($params, $type)
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

        if ($type == Consts::TYPE_TOP_GAINER_COIN) {
            return MarketGainer::insert($data);
        }
        return MarketLoser::insert($data);
    }

    private function topGainer($listGainer)
    {
        if (count($listGainer) > 0) {
            usort($listGainer, function ($firstItem, $secondItem) {
                return (int) $firstItem->changed_percent < (int) $secondItem->changed_percent;
            });

            $this->saveStatistic($listGainer, Consts::TYPE_TOP_GAINER_COIN);
        }
//        else {
//            $this->getLastestTop(Consts::TYPE_TOP_GAINER_COIN);
//        }
    }

    private function topLoser($listLoser)
    {
        if (count($listLoser) > 0) {
            usort($listLoser, function ($firstItem, $secondItem) {
                return (int) $firstItem->changed_percent > (int) $secondItem->changed_percent;
            });

            $this->saveStatistic($listLoser, Consts::TYPE_TOP_LOSER_COIN);
        }
//        else {
//            $this->getLastestTop(Consts::TYPE_TOP_LOSER_COIN);
//        }
    }

    private function getLastestTop($type)
    {
        $data = [];
        if ($type == Consts::TYPE_TOP_GAINER_COIN) {
            $markets = MarketGainer::whereIn('top', [1, 2, 3])
                ->orderBy('created_at', 'desc')
                ->limit(Consts::LIMIT_TOP_MARKET)
                ->get()
                ->unique('name');
        } else {
            $markets = MarketLoser::whereIn('top', [1, 2, 3])
                ->orderBy('created_at', 'desc')
                ->limit(Consts::LIMIT_TOP_MARKET)
                ->get()
                ->unique('name');
        }

        foreach ($markets as $market) {
            $dataParam = [
                'name' => $market->name,
                'top' => $market->top,
                'quantity' => $market->volume,
                'lastest_price' => $market->previous_price,
                'changed_percent' => $market->changed_percent,
                'created_at' => Carbon::now()->timestamp * 1000
            ];
            $data[] = $dataParam;
        }

        if ($type == Consts::TYPE_TOP_GAINER_COIN) {
            return MarketGainer::insert($data);
        }
        return MarketLoser::insert($data);
    }
}
