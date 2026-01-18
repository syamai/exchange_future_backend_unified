<?php

namespace App\Http\Services;

use App\Consts;
use App\Models\AccountProfileSetting;
use App\Models\User;
use App\Models\CoinSetting;
use App\Models\EnableTradingSetting;
use App\Events\BetaTesterStatusChanged;
use App\Notifications\BetaTesterActiveNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EnableTradingSettingService
{
    private $model;

    public function __construct()
    {
        $this->model = new EnableTradingSetting();
    }

    public function getUserPairTradingSetting($params)
    {
        $exist = $this->checkExistEnable($params['currency'], $params['coin'], $params['email']);
        if ($exist) {
            if ($exist->enable_trading == Consts::IGNORE_TRADING && now()->timestamp * 1000 >= floatval($exist->ignore_expired_at)) {
                $exist->ignore_expired_at = '';
                $exist->enable_trading = Consts::DISABLE_TRADING;
                $exist->save();
            }
            return $exist;
        }
        return null;
    }

    public function getPairCoinSetting($params)
    {
        return CoinSetting::query()
            ->when(!empty($params['currency']), function ($query) use ($params) {
                $query->where('coin_settings.currency', '=', $params['currency']);
            })
            ->when(!empty($params['coin']), function ($query) use ($params) {
                $query->where('coin_settings.coin', '=', $params['coin']);
            })
            ->first();
    }

    public function getEnableTradingSetting($params): \Illuminate\Database\Eloquent\Collection|array
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
        $query = DB::table('enable_trading_settings')
            ->when(array_key_exists('search_key', $params), function ($query) use ($params) {
                return $query->where('enable_trading_settings.email', 'like', '%' . $params['search_key'] . '%');
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

    public function createEnableTradingSetting($inputs): bool
    {
        if (!empty($inputs['allMarket'])) {
            $listPair = CoinSetting::get();
            foreach ($listPair as $pair) {
                if ($this->checkExistEnable($pair->currency, $pair->coin, $inputs['email'])) {
                    continue;
                }
                EnableTradingSetting::create([
                    'currency' => $pair->currency,
                    'coin' => $pair->coin,
                    'email' => $inputs['email'],
                    'enable_trading' => Consts::DISABLE_TRADING,
                ]);
            }
            return true;
        }
        if (!empty($inputs['inThisMarket'])) {
            $listPair = CoinSetting::where('currency', $inputs['currency'])->get();
            foreach ($listPair as $pair) {
                if ($this->checkExistEnable($pair->currency, $pair->coin, $inputs['email'])) {
                    continue;
                }
                EnableTradingSetting::create([
                    'currency' => $pair->currency,
                    'coin' => $pair->coin,
                    'email' => $inputs['email'],
                    'enable_trading' => Consts::DISABLE_TRADING,
                ]);
            }
            return true;
        }
        if (!$this->checkExistEnable($inputs['currency'], $inputs['coin'], $inputs['email'])) {
            EnableTradingSetting::create([
                'currency' => $inputs['currency'],
                'coin' => $inputs['coin'],
                'email' => $inputs['email'],
                'enable_trading' => Consts::DISABLE_TRADING,
            ]);
        } else {
            EnableTradingSetting::query()
                ->where('currency', $inputs['currency'])
                ->where('coin', $inputs['coin'])
                ->where('email', $inputs['email'])
                ->update([
                    'enable_trading' => $inputs['enable_trading'],
                ]);
        }

        return true;
        //MasterdataService::clearCacheOneTable('enable_trading_settings');
    }

    public function checkExistEnable($currency, $coin, $email)
    {
        return EnableTradingSetting::where('email', $email)
            ->where('currency', $currency)
            ->where('coin', $coin)
            ->first();
    }

    public function checkAllowTrading($currency, $coin, $email): bool
    {
        $user = User::query()->where('email', $email)->first();
        $allowAccountSetting = $user->AccountProfileSetting ?? null;

        if(!$allowAccountSetting->spot_trade_allow) return false;

        $exist = $this->checkExistEnable($currency, $coin, $email);
        if ($exist) {
            if ($exist->enable_trading == Consts::ENABLE_TRADING) {
                return true; // allow trading
            } elseif ($exist->enable_trading == Consts::DISABLE_TRADING) {
                return false; // block trading
            }
        }

        $coinSetting = CoinSetting::where('currency', $currency)
            ->where('coin', $coin)
            ->first();
        if ($coinSetting) {
            if ($coinSetting->is_enable) {
                return true; // allow trading
            }
            return false; // block trading
        }

        return false; // default block trading
    }

    public function updateEnableTradingSetting($inputs, $id)
    {
        $enableTradingSetting = $this->model->find($id);
        if (empty($enableTradingSetting)) {
            return $enableTradingSetting;
        }
        $user = User::where('email', $enableTradingSetting->email)->first();
        if ($inputs['enable_trading'] == Consts::ENABLE_TRADING && $enableTradingSetting->enable_trading == Consts::WAITING_TRADING) {
            $enableTradingSetting->is_beta_tester = true;
            $pair = strtoupper($enableTradingSetting->coin . '/' . $enableTradingSetting->currency);
            $user->notify(new BetaTesterActiveNotification(Consts::ENABLE_TRADING, $pair));
        }
        $enableTradingSetting->enable_trading = $inputs['enable_trading'];

        $enableTradingSetting->save();
        event(new BetaTesterStatusChanged($user->id, $enableTradingSetting));
        return $enableTradingSetting;
    }

    public function updateCoinSetting($inputs)
    {
        $coinSetting = CoinSetting::where('currency', $inputs['currency'])
            ->where('coin', $inputs['coin'])
            ->first();

        if (empty($coinSetting)) {
            return $coinSetting;
        }
        $coinSetting->is_enable = $inputs['is_enable'];
        $coinSetting->is_show_beta_tester = $inputs['is_show_beta_tester'];
        if ($coinSetting->is_enable) {
            $coinSetting->is_show_beta_tester = 0;
        }
        $coinSetting->save();
        MasterdataService::clearCacheOneTable('coin_settings');

        return $coinSetting;
    }

    public function deleteEnableTradingSetting($inputs, $id): bool
    {
        $enableTradingSetting = $this->model->find($id);
        if (!$enableTradingSetting) {
            return true;
        }
        $res = $enableTradingSetting->delete();

        if (!empty($inputs['allMarket'])) {
            $listPair = CoinSetting::get();
            foreach ($listPair as $pair) {
                $exist = $this->checkExistEnable($pair->currency, $pair->coin, $inputs['email']);
                if ($exist) {
                    $exist->delete();
                }
            }
            return true;
        }
        if (!empty($inputs['inThisMarket'])) {
            $listPair = CoinSetting::where('currency', $inputs['currency'])->get();
            foreach ($listPair as $pair) {
                $exist = $this->checkExistEnable($pair->currency, $pair->coin, $inputs['email']);
                if ($exist) {
                    $exist->delete();
                }
            }
            return true;
        }

        return $res;
    }
}
