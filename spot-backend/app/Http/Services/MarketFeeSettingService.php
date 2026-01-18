<?php

namespace App\Http\Services;

use App\Consts;
use App\Models\MarketFeeSetting;
use App\Models\CoinSetting;
use Illuminate\Support\Arr;

class MarketFeeSettingService
{
    private MarketFeeSetting $model;

    public function __construct(MarketFeeSetting $model)
    {
        $this->model = $model;
    }

    public function getMarketFeeSetting($params)
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        return CoinSetting::leftJoin('market_fee_setting', function ($join) {
            $join->on('coin_settings.currency', '=', 'market_fee_setting.currency');
            $join->on('coin_settings.coin', '=', 'market_fee_setting.coin');
        })
        ->when(!empty($params['currency']), function ($query) use ($params) {
            $query->where('coin_settings.currency', '=', $params['currency']);
        })
        ->select('market_fee_setting.id', 'coin_settings.currency', 'coin_settings.coin', 'market_fee_setting.fee_taker', 'market_fee_setting.fee_maker')
        ->paginate($limit);
    }

    public function updateMarketFeeSetting($input, $id)
    {
        $marketFeeSetting = $this->model->find($id);
        if (empty($marketFeeSetting)) {
            return $marketFeeSetting;
        }
        return $this->model->where('id', $id)->update($input);
    }
}
