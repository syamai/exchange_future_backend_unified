<?php

namespace App\Http\Services;

use App\Consts;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Utils;
use Carbon\Carbon;
use App\Models\Settings;
use App\Models\TradingVolumeRanking;
use App\Utils\BigNumber;
use App\Models\UserSecuritySetting;
use App\Models\User;

class AdminLeaderboardService
{
    private $sortType;
    public function updateLeaderboardSetting($params)
    {
        DB::beginTransaction();
        $type = $params["type"];
        $checked = $params["checked"];
        try {
            if ($type === "spot") {
                Settings::updateOrCreate(
                    ['key' => 'show_trading_volume_ranking_spot'],
                    ['key' => 'show_trading_volume_ranking_spot', 'value' => $checked]
                );
            } else {
                Settings::updateOrCreate(
                    ['key' => 'show_trading_volume_ranking_margin'],
                    ['key' => 'show_trading_volume_ranking_margin', 'value' => $checked]
                );
            }
            DB::commit();
            return true;
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            throw $ex;
        }
    }
    public function changeSetting($params): void
    {
        $key = Arr::get($params, 'key', "");
        $value = Arr::get($params, 'value', false);
        Settings::updateOrCreate(
            ['key' => $key],
            ['key' => $key, 'value' => $value]
        );
    }
    public function getSettingSelfTrading(): \Illuminate\Database\Eloquent\Collection|bool
    {
        $settings = Settings::all();
        if (!$settings) {
            return false;
        }
        return $settings;
    }
    public function getTopTradingVolumeRanking($params): array
    {
        if (!empty($params['currency'])) {
            $params['currency'] = strtolower($params['currency']);
        }
        $searchKey = 'search_key';
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        $page = Arr::get($params, 'page', 1);
        $type_ex = Arr::get($params, 'type', Consts::TYPE_EXCHANGE_BALANCE);

        $res = TradingVolumeRanking::where('coin', $params['currency'])
        ->where('type', $type_ex)
        ->when(!empty($params['start_date']), function ($query) use ($params) {
            $startDate = Carbon::createFromTimestamp($params['start_date']);
            return $query->where('created_at', '>=', $startDate);
        })
        ->when(!empty($params['end_date']), function ($query) use ($params) {
            $endDate = Carbon::createFromTimestamp($params['end_date']);
            return $query->where('created_at', '<', $endDate);
        })
        ->get();


        $data_raw = [];
        foreach ($res as $item) {
            if (!array_key_exists($item->user_id, $data_raw)) {
                unset($item->created_at);
                unset($item->updated_at);
                $data_raw[$item->user_id] = $item;
            } else {
                $data_raw[$item->user_id]->volume = BigNumber::new($data_raw[$item->user_id]->volume)->add($item->volume)->toString();
                $data_raw[$item->user_id]->btc_volume = BigNumber::new($data_raw[$item->user_id]->btc_volume)->add($item->btc_volume)->toString();
                $data_raw[$item->user_id]->self_trading = BigNumber::new($data_raw[$item->user_id]->self_trading)->add($item->self_trading)->toString();
                $data_raw[$item->user_id]->self_trading_btc_volume = BigNumber::new($data_raw[$item->user_id]->self_trading_btc_volume)->add($item->self_trading_btc_volume)->toString();
                $data_raw[$item->user_id]->trading_volume = BigNumber::new($data_raw[$item->user_id]->trading_volume)->add($item->trading_volume)->toString();
            }
        }
        $data_raw = array_values($data_raw);
        $key = $this->getKeySort($type_ex);
        $result = Utils::customSortByDesc($data_raw, $key);
        $index = 0;
        foreach ($result as &$item) {
            $index++;
            $item['ranking'] = $index;
        }
        $data = [];
        if (array_key_exists('search_key', $params)) {
            $searchKey = $params['search_key'];
            foreach ($result as &$r) {
                if (strpos($r['email'], $searchKey) !== false) {  // mustn't delete 'false'
                    array_push($data, $r);
                }
            }
        } else {
            $data = $result;
        }
        return Utils::customPaginate($page, $data, $limit);
    }

    public function getTopTradingVolumeRankingByUser($params): array
    {
        if (!empty($params['coin'])) {
            $params['coin'] = strtolower($params['coin']);
        }
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        $type_ex = Arr::get($params, 'type', Consts::TYPE_EXCHANGE_BALANCE);
        $settingService = new SettingService();
        logger("tuan tuan");
        logger($type_ex);
        $startDate = $settingService->getValueFromKey("trading_volume_start_${type_ex}");
        $endDate = $settingService->getValueFromKey("trading_volume_end_${type_ex}");
        $onlyKycUser = $settingService->getValueFromKey("trading_volume_kyc_${type_ex}");
        $startDate = Carbon::createFromTimestamp($startDate / 1000)->toDateString();
        $endDate = Carbon::createFromTimestamp($endDate / 1000)->toDateString();
        $res = TradingVolumeRanking::where('coin', $params['coin'])
        ->where('type', $type_ex)
        ->where('created_at', '>=', $startDate)
        ->where('created_at', '<=', $endDate)
        ->get();


        $data_raw = [];
        foreach ($res as $item) {
            $record = $this->getUserSecuritySettings($item->user_id);
            if (!$record) {
                continue;
            }

            if ($this->checkUserValid($onlyKycUser, $record->identity_verified)) {
                if (!array_key_exists($item->user_id, $data_raw)) {
                    $use_fake_name = @$record->use_fake_name ?? 0;
                    $fake_name = $this->getFakeName($item->user_id);
                    unset($item->created_at);
                    unset($item->updated_at);
                    $item->fake_name = $fake_name;
                    $item->use_fake_name = $use_fake_name;
                    $data_raw[$item->user_id] = $item;
                } else {
                    $data_raw[$item->user_id]->volume = BigNumber::new($data_raw[$item->user_id]->volume)->add($item->volume)->toString();
                    $data_raw[$item->user_id]->btc_volume = BigNumber::new($data_raw[$item->user_id]->btc_volume)->add($item->btc_volume)->toString();
                    $data_raw[$item->user_id]->self_trading = BigNumber::new($data_raw[$item->user_id]->self_trading)->add($item->self_trading)->toString();
                    $data_raw[$item->user_id]->self_trading_btc_volume = BigNumber::new($data_raw[$item->user_id]->self_trading_btc_volume)->add($item->self_trading_btc_volume)->toString();
                    $data_raw[$item->user_id]->trading_volume = BigNumber::new($data_raw[$item->user_id]->trading_volume)->add($item->trading_volume)->toString();
                }
            }
        }
        $data_raw = array_values($data_raw);
        $key = $this->getKeySort($type_ex);
        $result = Utils::customSortByDesc($data_raw, $key);
        $rsult = [
            "data" => array_slice($result, 0, $limit),
            "self_trading" => $key == "btc_volume" ? true : false,
            "start_date" => $startDate,
            "end_date" => $endDate,
            "kyc" => $onlyKycUser,
        ];
        return $rsult;
    }

    public function getLeaderboardSetting($params)
    {
        $type = $params["type"];
        $key = $type === "spot" ? 'show_trading_volume_ranking_spot' : 'show_trading_volume_ranking_margin';
        $query = DB::table('settings')
        ->select('key', 'value')
        ->where('key', $key);
        return $query->first();
    }

    public function getKeySort($type_ex): string
    {
        $key = 'btc_volume';
        $value = Settings::where('key', "self_trading_volume_{$type_ex}")->first();
        if (!$value) {
            return $key;
        }
        if ($value->value == 0) {
            $key = 'trading_volume';
        }
        return $key;
    }

    public function getUserSecuritySettings($userId): bool
    {
        $settings = UserSecuritySetting::where('id', $userId)->first();
        if (!$settings) {
            return false;
        }
        return $settings;
    }

    public function checkUserValid($onlyKycUser, $kyc): bool
    {
        if (!$onlyKycUser) {
            return true;
        }
        if (!$kyc) {
            return false;
        }
        return true;
    }

    public function getFakeName($userId): bool
    {
        $user = User::where('id', $userId)->first();
        if (!$user) {
            return false;
        }
        return $user->fake_name;
    }
}
