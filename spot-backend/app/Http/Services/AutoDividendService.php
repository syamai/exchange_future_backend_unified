<?php

namespace App\Http\Services;

use App\Consts;
use App\Models\AutoDividendSetting;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Utils\BigNumber;
use App\Http\Services\MasterdataService;

class AutoDividendService
{
    public function getPairs($market)
    {

        $result = [];
        $curencyCoins = MasterdataService::getOneTable('coin_settings');
        foreach ($curencyCoins as $currencyCoin) {
            if (strtoupper($currencyCoin->currency) == strtoupper($market)) {
                array_push($result, strtoupper($currencyCoin->coin));
            }
        }
        return $result;
    }

    public function updateAutoDividendSetting($data)
    {
        $data = (object) $data;
        $market = $data->market;
        $coin = $data->coin;
        $query = AutoDividendSetting::where('market', $market);
        if ($coin) {
            $query->where('coin', $coin);
        }
        $query->update([
            'enable' => $data->enable,
            'time_from' => $data->time_from,
            'time_to' => $data->time_to,
            'payout_amount' => $data->payout_amount,
            'lot' => $data->lot,
            'max_bonus' => $data->max_bonus,
            'payout_coin' => $data->payout_coin,
            'payfor' => $data->payfor,
        ]);
    }

    public function resetAutoDividendSetting($data)
    {
        $data = (object) $data;
        $market = $data->market;
        $coin = $data->coin;
    }

    public function getAutoDividendSetting($market, $settingFor)
    {
        $res = AutoDividendSetting::where('market', strtolower($market))
            ->where(['setting_for' => $settingFor, 'is_show' => 1])
            ->leftJoin('dividend_total_paid_each_pairs', function ($join) {
                $join->on('dividend_auto_settings.market', '=', 'dividend_total_paid_each_pairs.currency');
                $join->on('dividend_auto_settings.coin', '=', 'dividend_total_paid_each_pairs.coin');
                $join->on('dividend_auto_settings.payout_coin', '=', 'dividend_total_paid_each_pairs.payout_coin');
            })
            ->leftJoin('instruments', function ($join) {
                $join->on('dividend_auto_settings.market', '=', 'instruments.root_symbol');
                $join->on('dividend_auto_settings.coin', '=', 'instruments.symbol');
            })
            ->select(
                'dividend_auto_settings.market',
                'dividend_auto_settings.coin',
                'dividend_auto_settings.time_from',
                'dividend_auto_settings.time_to',
                'dividend_auto_settings.lot',
                'dividend_auto_settings.payfor',
                'dividend_auto_settings.payout_amount',
                'dividend_auto_settings.payout_coin',
                'dividend_auto_settings.enable',
                'dividend_auto_settings.max_bonus',
                'dividend_auto_settings.setting_for',
                'dividend_total_paid_each_pairs.total_paid'
            )
            ->where('instruments.state', '!=', Consts::INSTRUMENT_STATE_CLOSE)
            ->orderBy('dividend_auto_settings.coin')
            ->get();

        return $res;
    }

    public function getAllCoin()
    {
        return DB::table('coins')->get();
    }
}
