<?php
/**
 * Created by PhpStorm.
 * Date: 4/15/19
 * Time: 1:14 PM
 */

namespace App\Http\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\AmalSetting;
use App\Models\AmalTransaction;
use App\Models\AmalCashBack;
use App\Utils\BigNumber;
use App\Events\CoinCheckPriceAmlUpdated;
use App\Events\AmlSettingUpdated;
use App\Consts;

class AmlSettingService
{
    private $model;

    public function __construct(AmalSetting $model)
    {
        $this->model = $model;
    }

    public function index()
    {
        $res = $this->model->first();
        if (empty($res)) {
            $res = $this->model->create([]);
        }
        $res->fill([
            'btc_sold_money' => $this->getReceivedMoney('btc'),
            'eth_sold_money' => $this->getReceivedMoney('eth'),
            'usd_sold_money' => $this->getReceivedMoney('usd'),
            'usdt_sold_money' => $this->getReceivedMoney('usdt'),
        ]);
        return $res;
    }



    public function store($input)
    {
        return $this->model->create($input);
    }

    public function show($id)
    {
        return $this->model->find($id);
    }

    public function edit($id)
    {
        return $this->model->find($id);
    }

    private function getReceivedMoney($currency)
    {
        $usersPayment = AmalTransaction::where('currency', $currency)->sum('payment');
        $usersCashback = AmalCashBack::where('currency', $currency)->sum('bonus');
        return BigNumber::new($usersPayment)->sub($usersCashback)->toString();
    }

    public function update($input, $id)
    {
        $service = new CoinMarketCapService();
        $service->updateDataBase();

        $amlSetting = $this->model->find($id);
        if (empty($amlSetting)) {
            return $amlSetting;
        }
        $total = Arr::get($input, 'total');
        $amount = BigNumber::new($total)
            ->sub(new Bignumber($amlSetting->eth_sold_amount))
            ->sub(new Bignumber($amlSetting->usd_sold_amount))
            ->sub(new Bignumber($amlSetting->btc_sold_amount))
            ->sub(new Bignumber($amlSetting->usdt_sold_amount))
            ->toString();
        if ((new BigNumber(($amount)))->comp((0)) < 0) {
            $amount = 0;
        }
        $btc = DB::table('coin_market_cap_tickers')->where('symbol', 'BTC')->value('price_usd');
        $eth = DB::table('coin_market_cap_tickers')->where('symbol', 'ETH')->value('price_usd');
        $usdt = DB::table('coin_market_cap_tickers')->where('symbol', 'USDT')->value('price_usd');
        $amlSetting->fill([
            'total' => $total,
            'amount' => $amount,
            'usd_price' => Arr::get($input, 'usd_price'),
            'eth_price' => $this->getRound(BigNumber::new((Arr::get($input, 'usd_price')))->div($eth)->toString()),
            'btc_price' => $this->getRound(BigNumber::new((Arr::get($input, 'usd_price')))->div($btc)->toString()),
            'usdt_price' => $this->getRound(BigNumber::new((Arr::get($input, 'usd_price')))->div($usdt)->toString()),
            'amal_bonus_1' => Arr::get($input, 'amal_bonus_1'),
            'percent_bonus_1' => Arr::get($input, 'percent_bonus_1'),
            'amal_bonus_2' => Arr::get($input, 'amal_bonus_2'),
            'percent_bonus_2' => Arr::get($input, 'percent_bonus_2'),
            'referrer_commision_percent' => Arr::get($input, 'referrer_commision_percent'),
            'referred_bonus_percent' => Arr::get($input, 'referred_bonus_percent'),
        ]);
        $amlSetting->save();
        event(new CoinCheckPriceAmlUpdated($amlSetting->toArray()));
        event(new AmlSettingUpdated($amlSetting));
        return $amlSetting;
    }

    protected function getRound($input): string
    {
        return BigNumber::round($input, BigNumber::ROUND_MODE_FLOOR, Consts::DIGITS_NUMBER_PRECISION);
    }

    public function destroy($id)
    {
        $amlSetting = $this->model->find($id);
        if (empty($amlSetting)) {
            return $amlSetting;
        }
        return $this->model->where('id', $id)->delete();
    }

    public function getAmalSetting()
    {
        $key = "AmalSetting:current";
        $result = Cache::get($key);

        // If has cache, return
        if ($result) {
            return $result;
        }

        $result = AmalSetting::first();
        Cache::put($key, $result, 5*60); // 5 minutes

        return $result;
    }
}
