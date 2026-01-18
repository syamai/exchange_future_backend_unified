<?php

namespace App\Http\Services;

use App\Consts;
use App\Models\CoinSetting;
use App\Models\EnableFeeSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EnableFeeSettingService
{
    private $model;

    public function __construct()
    {
        $this->model = new EnableFeeSetting;
    }

    public function getEnableFeeSetting($params): \Illuminate\Database\Eloquent\Collection|array
    {
        return CoinSetting::query()
            ->when(!empty($params['currency']), function ($query) use ($params) {
                $query->where('coin_settings.currency', '=', $params['currency']);
            })
            ->get();
    }

    public function getUserListSetting($params): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        $query = DB::table('enable_fee_settings')
            ->when(array_key_exists('search_key', $params), function ($query) use ($params) {
                return $query->where('enable_fee_settings.email', 'like', '%' . $params['search_key'] . '%');
            })
            ->when(!empty($params['currency']), function ($query) use ($params) {
                $query->where('currency', '=', $params['currency']);
            })
            ->when(!empty($params['coin']), function ($query) use ($params) {
                $query->where('coin', '=', $params['coin']);
            })
            ->when(
                !empty($params['sort']) && !empty($params['sort_type']),
                function ($query) use ($params) {
                    return $query->orderBy($params['sort'], $params['sort_type']);
                },
                function ($query) use ($params) {
                    return $query->orderBy('updated_at', 'desc');
                }
            )
            ->orderBy('email');

        return $query->paginate($limit);
    }

    public function customPaginate($page, $data, $limit): \Illuminate\Pagination\LengthAwarePaginator
    {
        $offSet = ($page * $limit) - $limit;
        $itemsForCurrentPage = array_slice($data, $offSet, $limit, true);
        $result = new \Illuminate\Pagination\LengthAwarePaginator($itemsForCurrentPage, count($data), $limit, $page);

        return $result;
    }

    public function createEnableFeeSetting($inputs, $id): bool
    {
        if (!empty($inputs['allMarket'])) {
            $listPair = CoinSetting::get();
            foreach ($listPair as $pair) {
                if ($this->checkExistEnable($pair->currency, $pair->coin, $inputs['email'])) {
                    continue;
                } else {
                    EnableFeeSetting::create([
                        'currency' => $pair->currency,
                        'coin' => $pair->coin,
                        'email' => $inputs['email'],
                        'enable_fee' => Consts::DISABLE_FEE,//$inputs['enable_fee'],
                    ]);
                }
            }
            return true;
        }
        if (!empty($inputs['inThisMarket'])) {
            $listPair = CoinSetting::where('currency', $inputs['currency'])->get();
            foreach ($listPair as $pair) {
                if ($this->checkExistEnable($pair->currency, $pair->coin, $inputs['email'])) {
                    continue;
                } else {
                    EnableFeeSetting::create([
                        'currency' => $pair->currency,
                        'coin' => $pair->coin,
                        'email' => $inputs['email'],
                        'enable_fee' => Consts::DISABLE_FEE,//$inputs['enable_fee'],
                    ]);
                }
            }
            return true;
        }
        $enableFee = EnableFeeSetting::create([
            'currency' => $inputs['currency'],
            'coin' => $inputs['coin'],
            'email' => $inputs['email'],
            'enable_fee' => Consts::DISABLE_FEE,//$inputs['enable_fee'],
        ]);
        //MasterdataService::clearCacheOneTable('enable_fee_settings');

        return $enableFee;
    }

    public function checkExistEnable($currency, $coin, $email)
    {
        return EnableFeeSetting::where('email', $email)
            ->where('currency', $currency)
            ->where('coin', $coin)
            ->first();
    }

    public function updateEnableFeeSetting($inputs, $id)
    {
        $enableFeeSetting = $this->model->find($id);
        if (empty($enableFeeSetting)) {
            return $enableFeeSetting;
        }
        $data = [
            'enable_fee' => $inputs['enable_fee'],
        ];

        return $enableFeeSetting->update($data);
    }

    public function deleteEnableFeeSetting($inputs, $id): bool
    {
        $enableFeeSetting = $this->model->find($id);
        if (!$enableFeeSetting) {
            return true;
        }

        $res = $enableFeeSetting->delete();

        return $res;
    }
}
