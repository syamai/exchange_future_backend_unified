<?php

namespace App\Http\Services;

use App\Consts;
use App\Jobs\CashbackJob;
use App\Jobs\SendBonusByAdminJob;
use App\Models\Airdrop\AutoDividendHistory;
use App\Models\AirdropHistory;
use App\Models\AirdropHistoryLockBalance;
use App\Models\AirdropSetting;
use App\Models\AirdropUserSetting;
use App\Models\Airdrop\ManualDividendHistory;
use App\Models\DividendCashbackHistory;
use App\Models\DividendTotalBonus;
use App\Models\TotalBonusEachPair;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Models\User;
use App\Models\TradingVolumeRanking;
use Illuminate\Support\Facades\DB;
use App\Utils;
use Carbon\Carbon;
use App\Utils\BigNumber;
use App\Models\Settings;
use Illuminate\Support\Arr;

class AirdropService
{
    public function changeStatus($status)
    {
        $settings = AirdropSetting::first();
        if (!$settings) {
            foreach (Consts::COINS_ALLOW_AIRDROP as $coin) {
                $createAirdropSetting = AirdropSetting::create([
                    'enable' => 0,
                    'currency' => $coin,
                    'total_paid' => 0,
                    'status' => "inactive",
                    'unlock_percent' => 0,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }
            //default coin is btc
            AirdropSetting::where('currency', Consts::CURRENCY_BTC)
            ->update([
                'status' => "active",
            ]);
        }
        AirdropSetting::where('enable', '!=', $status)->update(['enable' => $status]);
        $current = AirdropSetting::where('status', Consts::AIRDROP_SETTING_ACTIVE)->first();
        cache(['airdrop:setting:current' => $current], config('airdrop.airdrop_setting_live_time_cache'));
        return $current;
    }

    public function changeStatusPayFee($status)
    {
        $settings = AirdropSetting::first();
        if (!$settings) {
            return false;
        }
        DB::table('airdrop_settings')->update(['enable_fee_amal' => $status]);
        $current = AirdropSetting::where('status', Consts::AIRDROP_SETTING_ACTIVE)->first();
        return $current;
    }

    public function changeStatusEnableWallet($status)
    {
        $settings = AirdropSetting::first();
        if (!$settings) {
            return false;
        }
        DB::table('airdrop_settings')->update(['enable_wallet_pay_fee' => $status]);
        return AirdropSetting::where('status', Consts::AIRDROP_SETTING_ACTIVE)->first();
    }

    public function getAirdropSetting()
    {
        return AirdropSetting::where('status', Consts::AIRDROP_SETTING_ACTIVE)->first();
    }

    public function getAirdropSettingToRender($timezone)
    {
        $setting = AirdropSetting::where('status', Consts::AIRDROP_SETTING_ACTIVE)->first();
        if (!$setting) {
            return null;
        }
        $hour = substr($setting->payout_time, 0, 2);
        $minute = substr($setting->payout_time, 3, 2);
        $temp = Carbon::now()->setTime($hour, $minute)->addHours($timezone);
        $setting->payout_time = $temp->format('H:i');

        return $setting;
    }

    public function getAllAirdropSetting()
    {
        return AirdropSetting::get();
    }

    public function getAirdropUserSetting($userId)
    {
        return AirdropUserSetting::where('user_id', $userId)->first();
    }

    public function getAidropSettingCurrency($currency)
    {
        return AirdropSetting::where('currency', $currency)->first();
    }

    public function updateAirdropCurrency($currency, $params)
    {
        AirdropSetting::where('currency', $currency)
            ->update([
                'status' => Consts::AIRDROP_SETTING_INACTIVE,
                'min_hold_amal' => $params['min_hold_amal'],
                'period' => $params['period'],
                'payout_time' => $params['payout_time'],
                'unlock_percent' => $params['unlock_percent'],
                'payout_amount' => $params["payout_amount_{$currency}"],
                'total_supply' => $params['total_supply'],
            ]);
    }

    public function updateAirdropSetting($params)
    {
        $currency = $params['currency'];
        foreach (Consts::COINS_ALLOW_AIRDROP as $coin) {
            $this->updateAirdropCurrency($coin, $params);
            if ($coin == $currency) {
                AirdropSetting::where('currency', $currency)
                              ->update(['status' => Consts::AIRDROP_SETTING_ACTIVE]);
            }
        }

        $setting = AirdropSetting::where('status', Consts::AIRDROP_SETTING_ACTIVE)->first();
        return $setting;
    }

    public function getListAirdropUserSetting($params)
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        $getListAirdropUserSetting = AirdropUserSetting::when(array_key_exists('email', $params), function ($query) use ($params) {
            $searchKey = $params['email'];
            return $query->where('email', 'like', '%' . $searchKey . '%');
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
        ->paginate($limit);
        return $getListAirdropUserSetting;
    }

    public function createAirdropUserSetting($params)
    {
        $email = $params['email'];
        $period = $params['period'];
        $unlock_percent = $params['unlock_percent'];
        $record = AirdropUserSetting::where('email', $email)->first();

        // TODO: Refactor: Validate in controller
        if ($record) {
            throw new HttpException(422, __('airdrop.user_was_exist'));
        }
        $user = User::where('email', $email)->first();
        if (!$user) {
            throw new HttpException(422, __("airdrop.cannot_find_email"));
        }
        $userId = $user->id;
        $createAirdropUserSetting = AirdropUserSetting::create([
            'user_id' => $userId,
            'email' => $email,
            'period' => $period,
            'unlock_percent' => $unlock_percent,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        return $createAirdropUserSetting;
    }

    public function deleteAirdropUserSetting($userId): bool
    {
        $setting = AirdropUserSetting::where('user_id', $userId)->first();
        if (!$setting) {
            return true;
        }

        $res = $setting->delete();

        return !!$res;
    }

    public function updateAirdropUserSetting($userId, $params): bool
    {
        $setting = AirdropUserSetting::where('user_id', $userId)->first();
        if (!$setting) {
            return false;
        }
        $email = $params['email'];

        // TODO: Refactor: Validate in controller
        $user = User::where('email', $email)->first();
        if (!$user) {
            throw new HttpException(422, __("airdrop.cannot_find_email"));
        }
        $res = $setting->fill($params)->save();
        cache(["airdrop:setting:user$userId" => $setting], config('airdrop.airdrop_setting_live_time_cache'));
        return $res;
    }

    public function getListAirdropHistory($params)
    {
        $searchKey = 'search_key';
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $res = AirdropHistoryLockBalance::when(!empty($params[$searchKey]), function ($q) use ($params, $searchKey) {
            return $q->where('email', 'like', '%' . $params[$searchKey] . '%');
        })
        ->when(!empty($params['currency']), function ($query) use ($params) {
            return $query->where('currency', $params['currency']);
        })
        ->when(!empty($params['start_date']), function ($query) use ($params) {
            $startDate = Carbon::createFromTimestamp($params['start_date']);
            return $query->where('created_at', '>=', $startDate);
        })
        ->when(!empty($params['end_date']), function ($query) use ($params) {
            $endDate = Carbon::createFromTimestamp($params['end_date']);
            return $query->where('created_at', '<', $endDate);
        })
        ->when(
            !empty($params['sort']) && !empty($params['sort_type']),
            function ($query) use ($params) {
                return $query->orderBy($params['sort'], $params['sort_type']);
            },
            function ($query) use ($params) {
                return $query->orderBy('created_at', 'desc');
            }
        )
        ->paginate($limit);

        return $res;
    }

    public function getAirdropPaymentHistory($params)
    {
        if (!empty($params['currency'])) {
            $params['currency'] = strtolower($params['currency']);
        }
        $searchKey = 'search_key';
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $res = AirdropHistory::when(!empty($params[$searchKey]), function ($q) use ($params, $searchKey) {
            return $q->where('email', 'like', '%' . $params[$searchKey] . '%');
        })
        ->when(!empty($params['currency']) && ($params['currency'] != 'all'), function ($query) use ($params) {
            return $query->where('currency', $params['currency']);
        })
        ->when(!empty($params['start_date']), function ($query) use ($params) {
            $startDate = Carbon::createFromTimestamp($params['start_date']);
            return $query->where('created_at', '>=', $startDate);
        })
        ->when(!empty($params['end_date']), function ($query) use ($params) {
            $endDate = Carbon::createFromTimestamp($params['end_date']);
            return $query->where('created_at', '<', $endDate);
        })
        ->when(
            !empty($params['sort']) && !empty($params['sort_type']),
            function ($query) use ($params) {
                return $query->orderBy($params['sort'], $params['sort_type']);
            },
            function ($query) use ($params) {
                return $query->orderBy('created_at', 'desc');
            }
        )
        ->paginate($limit);

        return $res;
    }

    /**
     * @param $params
     *
     * Aply bonus coin to list balance
     * @return bool
     */
    public function applyBonusBalance($params)
    {
        $lstUpdate = [];
        $collects = collect($params);
        $collects->each(function ($item, $key) use (&$lstUpdate) {
            $from = $item['filter_from'] == "0" ? null : (Carbon::parse($item['filter_from'])->format('Y-m-d') . ' 00:00:00');
            $to = $item['filter_to'] == "0" ? null : (Carbon::parse($item['filter_to'])->format('Y-m-d') . ' 23:59:59');
            if (!isset($item['type'])) {
                $item['type'] = Consts::TYPE_EXCHANGE_BALANCE;
            }
            array_push($lstUpdate, [
                'user_id' => $item['user_id'],
                'email' => $item['email'],
                'coin' => $item['type'] == Consts::TYPE_EXCHANGE_BALANCE ? $item['coin'] : strtoupper($item['market']),
                'market' => $item['type'] == Consts::TYPE_EXCHANGE_BALANCE ? $item['market'] : strtolower($item['coin']),
                'filter_from' => $from,
                'filter_to' => $to   ,
                'total_trade_volume' => $item['total_volume'],
                'bonus_amount' => $item['amount'],
                'balance' => $item['wallet'],
                'bonus_currency' => $item['bonus_currency'],
                'type' => $item['type'],
                'status' => $this->getStatus($item),
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'contest_id' => Arr::get($item, 'contest_id', null),
                'team_id' => Arr::get($item, 'team_id', null),
            ]);
            ManualDividendHistory::insert($lstUpdate);
            $lstUpdate = [];
            if ($this->getStatus($item) == Consts::TRANSACTION_STATUS_PENDING) {
                $this->sendBonus($item, $from, $to);
            }
        });
        return true;
    }

    public function sendBonus($item, $from, $to)
    {
        $data = [
            'user_id' => $item['user_id'],
            'currency' => strtolower($item['bonus_currency']),
            'amount' => $item['amount'],
            'filter_from' => $from,
            'filter_to' => $to,
            'trading_coin' => $item['coin'],
            'trading_currency' => $item['market'],
        ];

        if ($item['wallet'] == Consts::TYPE_AIRDROP_BALANCE || $item['wallet'] == Consts::TYPE_DIVIDEND_BONUS_BALANCE) {
            $this->createLockRecord($item);
        }
        try {
            return SendBonusByAdminJob::dispatch($data, $item['wallet'])->onQueue(Consts::QUEUE_AIRDROP);
        } catch (\Exception $exception) {
            return $exception;
        }
    }

    public function createLockRecord($data)
    {
        if ($data['wallet'] == Consts::TYPE_AIRDROP_BALANCE) {
            // $enableTypeSpecial = config('airdrop.enable_special_type_unlock');
            // if($enableTypeSpecial) {
                // return $this->createHistoryLockAirdropRecord($data, 1, 0);
            // }
            return $this->createHistoryLockAirdropRecord($data, 0, 0);
        }
        return $this->createHistoryLockAirdropRecord($data, 0, 1);
    }

    public function createHistoryLockAirdropRecord($bonus, $enableTypeSpecial, $dividendBonus)
    {
        $data = [
            'user_id' => $bonus['user_id'],
            'email' => $bonus['email'],
            'status' => Consts::AIRDROP_UNLOCKING,
            'total_balance' => $bonus['amount'],
            'amount' => 0,
            'unlocked_balance' => 0,
            'last_unlocked_date' => Carbon::now()->toDateString()
        ];
        if ($enableTypeSpecial) {
            $data['type'] = Consts::AIRDROP_TYPE_SPECIAL;
        }
        if ($dividendBonus) {
            $data['type'] = Consts::AIRDROP_TYPE_ADMIN;
        }
        return AirdropHistoryLockBalance::create($data);
    }

    private function getStatus($item)
    {
        if ($item['bonus_currency'] != Consts::CURRENCY_AMAL && ($item['wallet'] == Consts::TYPE_AIRDROP_BALANCE || $item['wallet'] == Consts::TYPE_DIVIDEND_BONUS_BALANCE)) {
            return Consts::AIRDROP_FAIL;
        }
        return Consts::TRANSACTION_STATUS_PENDING;
    }

    /**
     * @param $params
     * @param $userIds
     * @return array
     *
     * Get list send bonus history by user ids
     */
    public function getSendBonusHistoryStatusByUsers($params, $userIds)
    {
        $query = ManualDividendHistory::select('filter_from', 'filter_to', 'status', 'bonus_amount', 'balance', 'user_id');
        $query = $query->whereIn('user_id', $userIds);
        $query = $query->where('filter_from', '>=', $params['start_date']);
        $query = $query->where('filter_to', '<=', $params['end_date'] . ' 23:59:59');
        $rs = $query->get()->toArray();
        $data = [];
        array_walk($rs, function ($value, $key) use (&$data) {
            $data[$key] = $value['status'];
        });
        return $data;
    }

    /**
     * @param $params
     * @return mixed
     */
    public function getDividendManualHistory($params)
    {
        if (!empty($params['coin'])) {
            $params['coin'] = $params['type'] == 'margin' ? strtoupper($params['coin']) : strtolower($params['coin']);
        }
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $res = ManualDividendHistory::when(!empty($params['email']), function ($q) use ($params) {
            return $q->where('dividend_manual_history.email', 'like', '%' . $params['email'] . '%');
        })
        ->when(!empty($params['coin']) && ($params['coin'] != 'all' && $params['market'] != 'all'), function ($query) use ($params) {
            return $query->where('coin', $params['coin']);
        })
        ->when(!empty($params['balance']) && ($params['balance'] != 'all'), function ($query) use ($params) {
            return $query->where('balance', $params['balance']);
        })
        ->when(!empty($params['type']), function ($query) use ($params) {
            return $query->where('type', $params['type']);
        })
        ->when(!empty($params['market']) && ($params['market'] != 'all') && $params['type'] != 'margin', function ($query) use ($params) {
            return $query->where('market', strtolower($params['market']));
        })
        ->when(!empty($params['start_date']), function ($query) use ($params) {
            return $query->where('dividend_manual_history.created_at', '>=', $params['start_date']);
        })
        ->when(!empty($params['end_date']), function ($query) use ($params) {
            return $query->where('dividend_manual_history.created_at', '<=', $params['end_date']);
        })
        ->when(!empty($params['type_manual']), function ($query) {
            return $query->whereNull('dividend_manual_history.filter_from')
                    ->whereNull('dividend_manual_history.filter_to');
        })
        ->when(empty($params['type_manual']), function ($query) {
            return $query->whereNotNull('filter_from');
        })
        ->when(
            !empty($params['sort']) && !empty($params['sort_type']),
            function ($query) use ($params) {
                return $query->orderBy($params['sort'], $params['sort_type']);
            },
            function ($query) use ($params) {
                return $query->orderBy('created_at', 'desc');
            }
        );

        if (!empty($params['type_manual'])) {
            $res->select('dividend_manual_history.*', 'margin_contests.name as contest_name', 'margin_entry_team_leaderboards.name as team_name');
            $res->leftJoin('margin_contests', 'dividend_manual_history.contest_id', '=', 'margin_contests.id');
            $res->leftJoin('margin_entry_team_leaderboards', 'dividend_manual_history.team_id', '=', 'margin_entry_team_leaderboards.id');
            $res->when(!empty($params['contest']), function ($query) use ($params) {
                return $query->where('dividend_manual_history.contest_id', $params['contest']);
            });
            $res->when(!empty($params['team']), function ($query) use ($params) {
                return $query->where('dividend_manual_history.team_id', $params['team']);
            });
        }

        return $res->paginate($limit);
    }


    public function getTotalBonusDividend()
    {

        $res = DividendTotalBonus::get();
        return $res;
    }

    public function resetMaxBonus($params)
    {
        return TotalBonusEachPair::where('coin', strtolower($params['coin']))
            ->where('currency', strtolower($params['market']))
            ->where('payout_coin', $params['payout_coin'])
            ->update([
                'total_paid' => 0
            ]);
    }


   /**
     * @param $params
     *
     * Get list total trading volume by user by according date time range
     */
    public function getListTradingVolume($params)
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        $type_ex = Arr::get($params, 'type', Consts::TYPE_EXCHANGE_BALANCE);
        $key = $this->getKey($type_ex);
        $query = TradingVolumeRanking::groupBy(['trading_volume_ranking.user_id', 'trading_volume_ranking.email','trading_volume_ranking.type', 'setting.identity_verified']);

        if (isset($params['searchKey'])) {
            return  $this->getManualTradingByEmail($query, $key, $params);
        } elseif (isset($params['volume']) && $params['volume'] == 0) {
            return $this->getManualTradingByVolume($query, $key, $params, $limit);
        } elseif (isset($params['deposit_coin']) || isset($params['level_referrer']) || isset($params['amal_holding'])) {
            return $this->getManualDRH1($key, $params, $limit);
        } else {
            $query = $this->getManualTrading($query, $key, $params);
        }


        if (isset($params['sort']) && isset($params['sort_type'])) {
            $query = $query->orderBy($params['sort'], $params['sort_type']);
        } else {
            $query = $query->orderBy('trading_volume_ranking.email', 'DESC');
        }

        $query = $query->paginate($limit);
        foreach ($query as $item) {
            $item->referrer = null;
            $item->deposit = null;
            $item->deposit_coin = null;
            $item->amal_holding = null;
        }
        return $query;
    }


    public function getManualDRH1($key, $params, $limit)
    {
        $selectAfterUnion = "select t.id as user_id, t.email, sum(t.total_volume) as total_volume ";
        $selectTradingVolume = "(select `users`.`id`, `users`.`email`, SUM(ifnull({$key},0)) as total_volume";
        $selectDeposit = " union (select `users`.`id`, `users`.`email`, 0 as total_volume";
        $selectReferrer = "union (select `users`.`id`, `users`.`email`, 0 as total_volume";
        $selectKyc = "union (select `users`.`id`, `users`.`email`, 0 as total_volume";
        $selectAmalHolding = "union (select `users`.`id`, `users`.`email`, 0 as total_volume";
        $havingUnion = " ) as t group by t.id, t.email having (false ";

        $tradingVolumeBoby = " from `users` left join `trading_volume_ranking` on (`users`.`id` = `trading_volume_ranking`.`user_id`
        and `trading_volume_ranking`.`type` ='" . $params['type'] . "' and `trading_volume_ranking`.`created_at` >=  '" . $params['start_date']
        . "' and `trading_volume_ranking`.`created_at` <= '" . $params['end_date']. "'
         and `trading_volume_ranking`.`coin` = '" . $params['coin'] . "' and `trading_volume_ranking`.`market` = '" . $params['market'] . "' )
        group by `users`.`id`, `users`.`email`) ";

        if (isset($params["deposit_coin"])) {
            $selectAfterUnion = $selectAfterUnion . ", sum(t.deposit) as deposit";
            $selectTradingVolume = $selectTradingVolume. ", 0 as deposit";
            $selectReferrer = $selectReferrer. ", 0 as deposit";
            $selectAmalHolding = $selectAmalHolding. ", 0 as deposit";
            $selectKyc = $selectKyc. ", 0 as deposit";
            $selectDeposit = $selectDeposit. ", SUM(ifnull(transactions.amount,0))  as deposit";
            $depositBoby = " from `users` left join `transactions` on (`users`.`id` = `transactions`.`user_id`
            and (`transactions`.`from_address` not in ('dividend','PayBonusTrading','CashBack')
            or `transactions`.`from_address` is null)" ;
            if (isset($params['start_date'])) {
                $depositDate = "and `transactions`.`transaction_date` >= '" . $params['start_date'] . "' and `transactions`.`transaction_date`
                <= '" . $params['end_date'] . "'";
                $depositBoby = $depositBoby . $depositDate;
            }
            $depositBoby = $depositBoby . "and `transactions`.`currency` = '" . $params['deposit_coin'] . "' ) group by `users`.`id`, `users`.`email` )";
            $havingUnion = $havingUnion . " or (sum(t.deposit) >='" . $params['deposit'] . "')";
        }

        if (isset($params["referrer"])) {
            $selectAfterUnion = $selectAfterUnion . ", sum(t.referrer) as referrer";
            $selectTradingVolume = $selectTradingVolume. ", 0 as referrer";
            $selectReferrer = $selectReferrer. ", number_of_referrer_lv_{$params['level_referrer']} as referrer";
            $selectAmalHolding = $selectAmalHolding. ", 0 as referrer";
            $selectDeposit = $selectDeposit. ", 0  as referrer";
            $selectKyc = $selectKyc. ", 0  as referrer";
            $referrerBoby = " from `users` left join `referrer_multi_level_details` on (`users`.`id` = `referrer_multi_level_details`.`user_id` ) )";
            $havingUnion = $havingUnion . " or (sum(t.referrer) >='" . $params['referrer'] . "')";
        }

        if (isset($params["amal_holding"])) {
            $selectAfterUnion = $selectAfterUnion . ", sum(t.amal_holding) as amal_holding";
            $selectTradingVolume = $selectTradingVolume . ", 0 as amal_holding";
            $selectReferrer = $selectReferrer . ", 0 as amal_holding";
            $selectDeposit = $selectDeposit . ", 0 as amal_holding";
            $selectKyc = $selectKyc . ", 0 as amal_holding";
            if ($params['amal_holding'] == Consts::PERPETUAL_DIVIDEND_BALANCE) {
                $selectAmalHolding = $selectAmalHolding . " ,balance_bonus as amal_holding";
            } elseif ($params['amal_holding'] == Consts::DIVIDEND_BALANCE) {
                $selectAmalHolding = $selectAmalHolding . " ,balance as amal_holding";
            } else {
                $selectAmalHolding = $selectAmalHolding . " ,balance_bonus + balance as amal_holding";
            }
            $amalHoldingBoby = " from `users` left join `airdrop_amal_accounts` on (`users`.`id` = `airdrop_amal_accounts`.`id` ) )";
            $havingUnion = $havingUnion . " or (sum(t.amal_holding) >='" . $params['number_amal_holding'] . "')";
        }

        if (isset($params["kyc"])) {
            $selectAfterUnion = $selectAfterUnion . ", sum(t.kyc) as kyc";
            $selectTradingVolume = $selectTradingVolume. ", 0 as kyc";
            $selectReferrer = $selectReferrer. ", 0 as kyc";
            $selectDeposit = $selectDeposit. ", 0 as kyc";
            $selectAmalHolding = $selectAmalHolding. ", 0 as kyc";
            $selectKyc = $selectKyc . ", identity_verified as kyc";
            $kycBody = " from `users` left join `user_security_settings` on (`users`.`id` = `user_security_settings`.`id` ) )";
            $havingUnion = $havingUnion . "  ) and (sum(t.kyc) ='" . $params['kyc'] . "'";
        }



        //merger query

        $query = $selectAfterUnion . " from ( " ;

        $query = $query . $selectTradingVolume . $tradingVolumeBoby ;

        if (isset($params['deposit_coin'])) {
            $query = $query . $selectDeposit . $depositBoby;
        }

        if (isset($params['referrer'])) {
            $query = $query . $selectReferrer . $referrerBoby;
        }

        if (isset($params['amal_holding'])) {
            $query = $query . $selectAmalHolding . $amalHoldingBoby;
        }

        if (isset($params['kyc'])) {
            $query = $query . $selectKyc . $kycBody;
        }

        $query = $query . $havingUnion . ")";
        logger($query);

        if (isset($params['sort']) && isset($params['sort_type'])) {
            $query = $query . "order by " . $params['sort'] ." " . $params['sort_type'] ;
        }

        $page = @$params['page'] ?? 1;

        $data = DB::select(DB::raw($query));
        logger($data);
        $drh = Utils::customPaginate($page, $data, $limit);
        return $drh;
    }


    public function getManualDRH($query, $key, $params, $limit)
    {
        $queryDeposit = DB::table("users")
        ->select("users.id", "users.email", DB::raw("0 as total_volume"))
        ->leftJoin("transactions", "users.id", "=", "transactions.user_id")
        ->leftJoin('user_security_settings as setting', 'users.id', '=', 'setting.id')
        ->where('transactions.collect', Consts::DEPOSIT_TRANSACTION_COLLECTED_STATUS)
        ->where(function ($q) {
            $q->whereNotIn('transactions.from_address', Consts::DEPOSIT_BONUS);
            $q->orWhereNull('transactions.from_address');
        })
        ->groupBy("users.id", "users.email");
        if (isset($params['kyc'])) {
            $queryDeposit = $queryDeposit->where('setting.identity_verified', '=', $params['kyc']);
        }

        $queryReferrer = DB::table("users")
        ->select("users.id", "users.email", DB::raw("0 as total_volume"))
        ->join("referrer_multi_level_details", "users.id", "=", "referrer_multi_level_details.user_id")
        ->join('user_security_settings as setting', 'users.id', '=', 'setting.id');
        if (isset($params['kyc'])) {
            $queryReferrer = $queryReferrer->where('setting.identity_verified', '=', $params['kyc']);
        }

        $queryHolding = DB::table("users")
        ->select("users.id", "users.email", DB::raw("0 as total_volume"))
        ->join("airdrop_amal_accounts", "users.id", "=", "airdrop_amal_accounts.id")
        ->join('user_security_settings as setting', 'users.id', '=', 'setting.id');
        if (isset($params['kyc'])) {
            $queryHolding = $queryHolding->where('setting.identity_verified', '=', $params['kyc']);
        }

        $queryTradingVolume = DB::table("users")
        ->select("users.id", "users.email", DB::raw("SUM($key) as total_volume"))
        ->leftJoin("trading_volume_ranking", "users.id", "=", "trading_volume_ranking.user_id")
        ->leftJoin('user_security_settings as setting', 'trading_volume_ranking.user_id', '=', 'setting.id')
        ->groupBy("users.id", "users.email");
        if (isset($params['kyc'])) {
            $queryTradingVolume = $queryTradingVolume->where('setting.identity_verified', '=', $params['kyc']);
        }
        if (isset($params['type'])) {
            $queryTradingVolume = $queryTradingVolume->where('trading_volume_ranking.type', $params['type']);
        }
        if (isset($params['start_date'])) {
            $queryTradingVolume = $queryTradingVolume->where('trading_volume_ranking.created_at', '>=', $params['start_date']);
        }
        if (isset($params['end_date'])) {
            $queryTradingVolume = $queryTradingVolume->where('trading_volume_ranking.created_at', '<=', $params['end_date'] . ' 23:59:59');
        }
        if (isset($params['coin']) && $params['type'] == Consts::TYPE_MARGIN_BALANCE) {
            $queryTradingVolume = $queryTradingVolume->where('market', $params['coin']);
        }
        if (isset($params['coin']) && $params['type'] == Consts::TYPE_EXCHANGE_BALANCE) {
            $queryTradingVolume = $queryTradingVolume->where('trading_volume_ranking.coin', $params['coin']);
            $queryTradingVolume = $queryTradingVolume->where('trading_volume_ranking.market', $params['market']);
        }
        if (isset($params['deposit_coin'])) {
            $queryTradingVolume->addSelect(DB::raw("0 as deposit"));
            $queryHolding->addSelect(DB::raw("0 as deposit"));
            $queryReferrer->addSelect(DB::raw("0 as deposit"));

            $queryDeposit->addSelect(DB::raw("SUM(transactions.amount) as deposit"))
                        ->where('transactions.transaction_date', '>=', $params['start_date'])
                        ->where('transactions.transaction_date', '<=', $params['end_date'] . ' 23:59:59')
                        ->where('transactions.currency', $params['deposit_coin']);
            $queryTradingVolume->union($queryDeposit);
        }

        if (isset($params['referrer'])) {
            $queryTradingVolume->addSelect(DB::raw("0 as referrer"));
            $queryHolding->addSelect(DB::raw("0 as referrer"));
            $queryDeposit->addSelect(DB::raw("0 as referrer"));

            $queryReferrer->addSelect(DB::raw("referrer_multi_level_details.number_of_referrer_lv_{$params['level_referrer']} as referrer"));
            $queryTradingVolume->union($queryReferrer);
        }

        if (isset($params['amal_holding'])) {
            $type = $params["amal_holding"];

            $queryTradingVolume->addSelect(DB::raw("0 as amal_holding"));
            $queryDeposit->addSelect(DB::raw("0 as amal_holding"));
            $queryReferrer->addSelect(DB::raw("0 as amal_holding"));


            if ($type == Consts::PERPETUAL_DIVIDEND_BALANCE) {
                $queryHolding->addSelect(DB::raw("airdrop_amal_accounts.balance_bonus as amal_holding"));
            } elseif ($type == Consts::DIVIDEND_BALANCE) {
                $queryHolding->addSelect(DB::raw("airdrop_amal_accounts.balance as amal_holding"));
            } else {
                $queryHolding->addSelect(DB::raw("airdrop_amal_accounts.balance + airdrop_amal_accounts.balance_bonus as amal_holding"));
            }


            $queryTradingVolume->union($queryHolding);
        }

        $rs = $queryTradingVolume->get()->toArray();
        json_decode(json_encode($rs), true);
        logger("rs");
        logger($rs);

        usort($rs, function ($gt1, $gt2) {
            return $gt1->id > $gt2->id;
        });
        $firstObj = $rs[0] ?? null;
        $lists = [];
        for ($i = 1; $i < count($rs); $i++) {
            if ($rs[$i]->id == $firstObj->id) {
                $firstObj->total_volume = BigNumber::new($firstObj->total_volume)->add($rs[$i]->total_volume)->toString();
                if (isset($params['deposit_coin'])) {
                    $firstObj->deposit = BigNumber::new($firstObj->deposit)->add($rs[$i]->deposit)->toString();
                }
                if (isset($params['referrer'])) {
                    $firstObj->referrer = BigNumber::new($firstObj->referrer)->add($rs[$i]->referrer)->toString();
                }
                if (isset($params['amal_holding'])) {
                    $firstObj->amal_holding = BigNumber::new($firstObj->amal_holding)->add($rs[$i]->amal_holding)->toString();
                }
            } else {
                $firstObj->user_id = $firstObj->id;
                array_push($lists, $firstObj);
                $firstObj = $rs[$i];
            }
        }
        if ($firstObj) {
            $firstObj->user_id = $firstObj->id;
            array_push($lists, $firstObj);
        }

        logger("lists");
        logger($lists);
        if (count($lists) != 0) {
            $lists = array_filter($lists, function ($item) use ($params) {
                if (isset($params['deposit_coin'])) {
                    if ($item->deposit >= $params['deposit']) {
                        return true;
                    }
                }

                if (isset($params['referrer'])) {
                    if ($item->referrer >= $params['referrer']) {
                        return true;
                    }
                }

                if (isset($params['amal_holding'])) {
                    if ($item->amal_holding >= $params['number_amal_holding']) {
                        return true;
                    }
                }
                return false;
            });
        }

        $page = @$params['page'] ?? 1;

        $drh = Utils::customPaginate($page, $lists, $limit);
        return $drh;
    }
    public function getManualTrading($query, $key, $params)
    {
        $query = TradingVolumeRanking::groupBy(['trading_volume_ranking.user_id', 'email', 'market', 'coin', 'setting.identity_verified']);
        $query->leftJoin('user_security_settings as setting', 'trading_volume_ranking.user_id', '=', 'setting.id')
                ->select('trading_volume_ranking.user_id', 'email', DB::raw("SUM($key) AS total_volume"), 'market', 'coin', 'setting.identity_verified AS verified');
        if (isset($params['type'])) {
            $query = $query->where('trading_volume_ranking.type', $params['type']);
            if (isset($params['coin']) && $params['type'] == Consts::TYPE_MARGIN_BALANCE) {
                $query = $query->where('market', $params['coin']);
            }
            if (isset($params['coin']) && $params['type'] == Consts::TYPE_EXCHANGE_BALANCE) {
                $query = $query->where('coin', $params['coin']);
                $query = $query->where('market', $params['market']);
            }
        }
        if (isset($params['start_date'])) {
            $query = $query->where('trading_volume_ranking.created_at', '>=', $params['start_date']);
        }
        if (isset($params['end_date'])) {
            $query = $query->where('trading_volume_ranking.created_at', '<=', $params['end_date'] . ' 23:59:59');
        }
        if (isset($params['volume'])) {
            $query = $query->having(DB::raw("SUM($key)  "), '>=', $params['volume']);
        }
        if (isset($params['kyc'])) {
            $query = $query->where('setting.identity_verified', '=', $params['kyc']);
        }
        return $query;
    }
    public function getManualTradingByVolume($query, $key, $params, $limit)
    {


        logger("vao day getManualTradingByVolume");
        $query = "select users.id as user_id, users.email,sum(ifnull({$key}, 0)) as total_volume, users.created_at";
        if (isset($params['kyc'])) {
            $query = $query . ", user_security_settings.identity_verified ";
        }
        $query = $query . " from `users`  left join `trading_volume_ranking` on (`users`.`id` = `trading_volume_ranking`.`user_id`
        and `trading_volume_ranking`.`type` ='" . $params['type'] . "' and `trading_volume_ranking`.`created_at` >=  '" . $params['start_date']
        . "' and `trading_volume_ranking`.`created_at` <= '" . $params['end_date']. "')";
        if (isset($params['kyc'])) {
            $query = $query . " left join `user_security_settings` on (`users`.`id` = `user_security_settings`.`id`)
            where `user_security_settings`.`identity_verified` = '" . $params['kyc'] . "' ";
        }
        $query = $query . " group by `users`.`id`, `users`.`email`, users.created_at ";
        if (isset($params['kyc'])) {
            $query = $query . ", user_security_settings.identity_verified ";
        }
        $query = $query . "having sum(ifnull({$key}, 0)) >= 0
        and `users`.`created_at` >=  '" . $params['start_date']
        . "' and `users`.`created_at` <= '" . $params['end_date']. "'";

        if (isset($params['sort']) && isset($params['sort_type'])) {
            $query = $query . "order by " . $params['sort'] ." " . $params['sort_type'] ;
        }

        $page = @$params['page'] ?? 1;

        $data = DB::select(DB::raw($query));
        logger($data);
        $drh = Utils::customPaginate($page, $data, $limit);
        return $drh;
    }
    public function getManualTradingByEmail($query, $key, $params)
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        $searchKey = $params['searchKey'];
        $listUsers = User::where(function ($q) use ($searchKey) {
            $exportUser = explode(",", $searchKey);
            $trimArr = function ($item) {
                $item = trim($item);
                return str_replace("+", "\\+", $item);
            };
            $exportUser = array_map($trimArr, $exportUser);

            $implodeUser = implode("|", $exportUser);
            $q->orWhere('email', 'REGEXP', $implodeUser);
            $q->orWhere('id', 'REGEXP', $implodeUser);
        });
        $listEmailUsers = $listUsers->pluck('email');
        $listIdEmail = $listUsers->pluck('id', 'email');
        $query->leftJoin('user_security_settings as setting', 'trading_volume_ranking.user_id', '=', 'setting.id');
        $query->select('user_id', 'email', 'type', DB::raw("SUM($key) AS total_volume"))
        ->whereIn('trading_volume_ranking.email', $listEmailUsers);
        if (isset($params['type'])) {
            $query = $query->where('type', $params['type']);
        }
        if (isset($params['start_date'])) {
            $query = $query->where('trading_volume_ranking.created_at', '>=', $params['start_date']);
        }
        if (isset($params['end_date'])) {
            $query = $query->where('trading_volume_ranking.created_at', '<=', $params['end_date'] . ' 23:59:59');
        }
        if (isset($params['coin']) && $params['type'] == Consts::TYPE_MARGIN_BALANCE) {
            $query = $query->where('market', $params['coin']);
        }
        if (isset($params['coin']) && $params['type'] == Consts::TYPE_EXCHANGE_BALANCE) {
            $query = $query->where('coin', $params['coin']);
            $query = $query->where('market', $params['market']);
        }
        $data = $query->get()->toArray();
        $result = [];
        foreach ($listEmailUsers as $user) {
            $item = Arr::first($data, function ($value) use ($user) {
                return $value["email"] == $user;
            });
            if (!$item) {
                $item = [
                    'user_id' => $listIdEmail[$user],
                    'email' => $user,
                    'type' => "",
                    'total_volume' => "0"
                ];
            }

            array_push($result, $item);
        }
        return Utils::customSortData($params, $result, $limit, 'total_volume');
    }
    public function getCashbackHistory($params)
    {
        $searchKey = 'search_key';
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

         $res = DividendCashbackHistory::when(!empty($params[$searchKey]), function ($q) use ($params, $searchKey) {
            return $q->where('email', 'like', '%' . $params[$searchKey] . '%');
         })
        ->when(!empty($params['start_date']), function ($query) use ($params) {
            // // $startDate = Carbon::createFromTimestamp($params['start_date']);
            return $query->where('created_at', '>=', $params['start_date']);
        })
        ->when(!empty($params['end_date']), function ($query) use ($params) {
            // $endDate = Carbon::createFromTimestamp($params['end_date']);
            return $query->where('created_at', '<', $params['end_date']);
        })
        ->when(
            !empty($params['sort']) && !empty($params['sort_type']),
            function ($query) use ($params) {
                return $query->orderBy($params['sort'], $params['sort_type']);
            },
            function ($query) use ($params) {
                return $query->orderBy('created_at', 'desc');
            }
        )
        ->paginate($limit);

         return $res;
    }

     /**
     * @param $params
     *
     * Aply bonus coin to list balance
     * @return bool
     */
    public function refundBonusBalance($params)
    {
        // $lstUpdate = [];
        $collects = collect($params);
        $collects->each(function ($item, $key) {
            if ($item['amount'] == 0) {
                return true;
            }
            $rand = random_int(1, 99999);
            $cashbackId = Carbon::now()->timestamp . $rand;
            $lstUpdate = [
                'cashback_id' => $cashbackId,
                'user_id' => $item['user_id'],
                'email' => $item['email'],
                'amount' => $item['amount'],
                'status' => Consts::ORDER_STATUS_PENDING,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s')
            ];
            $data = [
                'user_id' => $item['user_id'],
                'email' => $item['email'],
                'amount' => $item['amount'],
            ];
            DividendCashbackHistory::insert($lstUpdate);
            logger($lstUpdate);
            logger("insert ok");
            try {
                return CashbackJob::dispatch($data, $cashbackId)->onQueue(Consts::QUEUE_AIRDROP);
            } catch (\Exception $exception) {
                return $exception;
            }
        });
        return true;
    }

    /**
     * @param $params
     *
     * Update total bonus in Dividend
     * @return bool
     */
    public function updateTotalBonus($amount, $coin): bool
    {
        return DividendTotalBonus::where('coin', strtolower($coin))
            ->update([
                'total_bonus' => DB::raw('total_bonus + ' . $amount)
            ]);
    }

    public function getKey($type): string
    {
        $settings = Settings::where('key', "self_trading_manual_dividend_{$type}")->first();
        if (!$settings) {
            return 'trading_volume';
        }
        if ($settings->value == 0) {
            return 'trading_volume';
        }
        return 'btc_volume';
    }
    /**
     * @param $params
     *
     * Update total bonus has paid each pair in Dividend
     * @return bool
     */
    public function updateTotalBonusInPair($amount, $bonusCurrency, $coin, $currency): bool
    {
        return TotalBonusEachPair::where('coin', strtolower($coin))
            ->where('currency', strtolower($currency))
            ->where('payout_coin', $bonusCurrency)
            ->update([
                'total_paid' => DB::raw('total_paid + ' . $amount)
            ]);
    }

    public function createTotalPaidEachPair($coin, $currency, $payoutCoin)
    {
        return TotalBonusEachPair::create([
            'coin' => $coin,
            'currency' => $currency,
            'total_paid' => 0,
            'payout_coin' => $payoutCoin
        ]);
    }

    /**
     * @param $params
     * @return mixed
     */
    public function getDividendAutoHistory($params): mixed
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        $res = AutoDividendHistory::when(!empty($params['email']), function ($q) use ($params) {
            return $q->where('email', 'like', '%' . $params['email'] . '%');
        })
        ->when(!empty($params['currency']) && ($params['currency'] != 'all' && $params['market'] != 'all'), function ($query) use ($params) {
            return $query->where('currency', $params['currency']);
        })
        ->when(!empty($params['type']), function ($query) use ($params) {
            return $query->where('type', $params['type']);
        })
        ->when(!empty($params['bonus_wallet'])  && ($params['bonus_wallet'] != 'all'), function ($query) use ($params) {
            return $query->where('bonus_wallet', $params['bonus_wallet']);
        })
        ->when(!empty($params['market']) && ($params['market'] != 'all'), function ($query) use ($params) {
            return $query->where('market', $params['market']);
        })
        ->when(!empty($params['start_date']), function ($query) use ($params) {
            $startDate = Carbon::createFromTimestamp($params['start_date']);
            return $query->where('bonus_date', '>=', $startDate);
        })
        ->when(!empty($params['end_date']), function ($query) use ($params) {
            $endDate = Carbon::createFromTimestamp($params['end_date']);
            return $query->where('bonus_date', '<', $endDate);
        })
        ->when(
            !empty($params['sort']) && !empty($params['sort_type']),
            function ($query) use ($params) {
                return $query->orderBy($params['sort'], $params['sort_type']);
            },
            function ($query) use ($params) {
                return $query->orderBy('created_at', 'desc');
            }
        )
        ->paginate($limit);

        return $res;
    }
}
