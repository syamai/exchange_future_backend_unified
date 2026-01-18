<?php

namespace App\Http\Services;

use App\Consts;
use App\Events\UserWithdrawalSettingEvent;
use App\Models\CoinsConfirmation;
use App\Models\EnableWithdrawalSetting;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EnableWithdrawalSettingService
{
    private $model;

    public function __construct()
    {
        $this->model = new EnableWithdrawalSetting;
    }

    public function getEnableWithdrawalSetting($params)
    {
        // return Coin::query()->get();

        // return CoinSetting::query()
        //     ->when(!empty($params['currency']), function ($query) use ($params) {
        //         $query->where('coin_settings.currency', '=', $params['currency']);
        //     })
        //     ->get();
    }

    public function getWithdrawSetting($params): \Illuminate\Support\Collection
    {
        $email = $params['email'];
        $query = DB::table('enable_withdrawal_settings')->where('email', $email);

        return $query->get();
    }

    public function getUserListSetting($params): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        $query = DB::table('enable_withdrawal_settings')
            ->when(array_key_exists('search_key', $params), function ($query) use ($params) {
                return $query->where('enable_withdrawal_settings.email', 'like', '%' . $params['search_key'] . '%');
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

    public function createEnableWithdrawalSetting($inputs, $id)
    {
        if (!empty($inputs['allCoins'])) {
            $listPair = CoinsConfirmation::select('id', 'coin')->get();
            foreach ($listPair as $pair) {
                if ($this->checkExistEnable($pair->coin, $inputs['email'])) {
                    continue;
                }
                $res = EnableWithdrawalSetting::create([
                    'coin' => $pair->coin,
                    'email' => $inputs['email'],
                    'enable_withdrawal' => Consts::DISABLE_WITHDRAWAL,
                ]);
                $this->sendEventUserWithdrawalSetting($inputs['email'], $res);
            }
            return true;
        }
        $res = EnableWithdrawalSetting::create([
            'coin' => $inputs['coin'],
            'email' => $inputs['email'],
            'enable_withdrawal' => Consts::DISABLE_WITHDRAWAL,
        ]);
        $this->sendEventUserWithdrawalSetting($inputs['email'], $res);
        //MasterdataService::clearCacheOneTable('enable_withdrawal_settings');

        return $res;
    }

    protected function sendEventUserWithdrawalSetting($email, $data): void
    {
        $userId = User::where('email', $email)->first()->id;
        event(new UserWithdrawalSettingEvent($userId, $data));
    }

    public function checkExistEnable($coin, $email)
    {
        return EnableWithdrawalSetting::where('email', $email)
            ->where('coin', $coin)
            ->first();
    }

    public function checkBlockWithdrawal($coin, $email): bool
    {
        $exist = $this->checkExistEnable($coin, $email);
        if (!$exist || !$exist->enable_withdrawal == Consts::ENABLE_WITHDRAWAL) {
            return false;   // allow withdrawal
        }

        return true; // block withdrawal
    }

    public function updateEnableWithdrawalSetting($inputs, $id)
    {
        $enableFeeSetting = $this->model->find($id);
        if (empty($enableFeeSetting)) {
            return $enableFeeSetting;
        }
        $data = [
            'enable_withdrawal' => $inputs['enable_withdrawal'],
        ];

        return $enableFeeSetting->update($data);
    }

    public function deleteEnableWithdrawalSetting($inputs, $id): bool
    {
        $enableFeeSetting = $this->model->find($id);
        if (!$enableFeeSetting) {
            return true;
        }

        $res = $enableFeeSetting->delete();

        $enableFeeSetting->isDelete = true;
        $this->sendEventUserWithdrawalSetting($enableFeeSetting->email, $enableFeeSetting);

        return $res;
    }
}
