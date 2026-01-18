<?php

namespace App\Http\Services;

use App\Consts;
use App\Enums\StatusVoucher;
use App\Events\UserNotificationUpdated;
use App\Facades\CheckFa;
use App\Facades\FormatFa;
use App\Jobs\SendBalance;
use App\Jobs\SendBalanceLogToWallet;
use App\Mail\MailVerifyAntiPhishing;
use App\Models\AirdropHistoryLockBalance;
use App\Models\CoinsConfirmation;
use App\Models\KYC;
use App\Models\MultiReferrerDetails;
use App\Models\SpotCommands;
use App\Models\SumsubKYC;
use App\Models\TransferHistory;
use App\Models\User;
use App\Models\UserAntiPhishing;
use App\Models\UserConnectionHistory;
use App\Models\UserDeviceRegister;
use App\Models\UserFeeLevel;
use App\Models\UserNotificationSetting;
use App\Models\UserSecuritySetting;
use App\Models\UserSetting;
use App\Models\UserTransaction;
use App\Models\UserWithdrawalAddress;
use App\Notifications\BanAccount;
use App\Notifications\ResetPassword;
use App\Utils;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserService
{
    const USER_REFERRAL_COMMISSION_LIMIT = 3;
    const CACHE_LIVE_TIME = 300; // 5 minutes

    public function getUserAccounts($userId, $store = null): array
    {
        // All balances
        $currencies = CoinsConfirmation::query()->select('coin')
			->where(function ($q) {
				$q->orWhere('is_withdraw', 1);
				$q->orWhere('is_deposit', 1);
			})
			->pluck('coin');

        if (!$store) {
            return $this->queryAllBalances($userId, $currencies);
        }

        // With 1 balance type
        $result = $this->queryBalance($userId, $store, $currencies);
        $mappingResult = [];
        foreach ($result as $coin => $balanceItem) {
            $mappingResult[$store][$coin] = $balanceItem;
        }

        return $mappingResult;
    }

    private function queryAllBalances($userId, $currencies): array
    {
        $balanceTypes = [
            Consts::TYPE_MAIN_BALANCE,
//            Consts::TYPE_MAM_BALANCE,
            Consts::TYPE_EXCHANGE_BALANCE,
//            Consts::TYPE_AIRDROP_BALANCE,
        ];
        $isSpotMainBalance = env('DEPOSIT_WITHDRAW_SPOT_BALANCE', false);
        if ($isSpotMainBalance) {
            $balanceTypes = [
                Consts::TYPE_EXCHANGE_BALANCE
            ];
        }

        $result = [];

        foreach ($balanceTypes as $balanceType) {
            $storeData = $this->queryBalance($userId, $balanceType, $currencies);

            foreach ($currencies as $currency) {
                $result[$balanceType][$currency] = $storeData[$currency];
            }
        }

        //unset($result[Consts::TYPE_EXCHANGE_BALANCE]['xrp']);
        //unset($result[Consts::TYPE_EXCHANGE_BALANCE]['ltc']);
        return $result;
    }

    private function queryBalance($userId, $balanceType, $currencies): array
    {
        $result = [];
        // TODO: Check logic with MAM
        if ($balanceType == Consts::TYPE_MAM_BALANCE) {
            foreach ($currencies as $currency) {
                $result[$currency] = (object)[
                    'balance' => 0,
                    'available_balance' => 0
                ];
            }
            return $result;
        }

        // With other balance type
        foreach ($currencies as $currency) {
            if ($balanceType == Consts::TYPE_AIRDROP_BALANCE && !in_array($currency, Consts::AIRDROP_TABLES)) {
                $result[$currency] = (object)[
                    'balance' => 0,
                    'available_balance' => 0
                ];
                continue;
            }

            $currencyTable = $this->getTableWithType($balanceType, $currency);

            // Build query
            $query = DB::connection('master')->table($currencyTable);
            $query = $query->addSelect(
                $currencyTable . '.balance',
                $currencyTable . '.available_balance'
            );

            if ($balanceType == Consts::TYPE_MAIN_BALANCE) {
                if ($currency != Consts::CURRENCY_USD) {
                    $query = $query->addSelect($currencyTable . '.blockchain_address')
                        ->addSelect($currencyTable . '.usd_amount');
                }
                if (Consts::CURRENCY_XRP == $currency || Consts::CURRENCY_EOS == $currency) {
                    $query = $query->addSelect($currencyTable . '.blockchain_sub_address');
                }
            }

            if ($balanceType == Consts::TYPE_AIRDROP_BALANCE) {
                $query = $query->addSelect(
                    $currencyTable . '.last_unlock_date',
                    $currencyTable . '.balance_bonus',
                    $currencyTable . '.available_balance_bonus'
                );
            }

            $query = $query->where($currencyTable . '.id', $userId);

            $result[$currency] = $query->first();
        }

        return $result;
    }

    public function getUserByEmail($email)
    {
        return User::where('email', $email)->first();
    }

    public function updatePasswordByEmail($email, $currentTime, $password)
    {
        //$passwordBcrypt = Utils::encrypt($password);
        $passwordBcrypt = bcrypt($password);
        return User::where('email', $email)
            ->update([
                'password' => $passwordBcrypt,
                'updated_at' => $currentTime,
                'fingerprint' => null,
                'faceID' => null,
            ]);
    }

    public function requestToFutureGetBalance()
    {
        $futureBaseUrl = env('FUTURE_API_URL');
        $path = $futureBaseUrl . '/api/v1/balance/user-balances';
        $client = new Client();
        $resAccess = $client->request('GET', $path, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'futureUser' => env('FUTURE_USER'),
                'futurePassword' => env('FUTURE_PASSWORD')
            ]
        ]);

        if ($resAccess->getStatusCode() >= 400) {
            throw new \ErrorException(json_encode($resAccess->getBody()));
        }

        return json_decode($resAccess->getBody()->getContents());
    }

    public function getTotalBalances()
    {
        $coinBalances = $this->requestToFutureGetBalance()->data ?? null;
        $result = [];
        foreach (MasterdataService::getCurrenciesAndCoins() as $currency) {
            if (Schema::hasTable($currency . '_accounts')) {
                $query = DB::table($currency . '_accounts')
                    ->join("spot_{$currency}_accounts", "spot_{$currency}_accounts.id", $currency . '_accounts.id');
//            if ($currency === Consts::CURRENCY_BTC) {
//                $result[$currency] = $query->join("margin_accounts", "margin_accounts.id", $currency . '_accounts.id')
//                    ->selectRaw("(SUM({$currency}_accounts.balance)
//                    + SUM(margin_accounts.balance)
//                    + SUM(spot_{$currency}_accounts.balance)) as balances,
//                    (SUM({$currency}_accounts.available_balance)
//                    + SUM(margin_accounts.available_balance)
//                    + SUM(spot_{$currency}_accounts.available_balance)) as available_balances")
//                    ->get();
//                continue;
//            }
//            if ($currency === Consts::CURRENCY_AMAL) {
//                $result[$currency] = $query->join("{$currency}_margin_accounts", "{$currency}_margin_accounts.id", $currency . '_accounts.id')
//                    ->join("airdrop_{$currency}_accounts", "airdrop_{$currency}_accounts.id", $currency . '_accounts.id')
//                    ->selectRaw("(SUM({$currency}_accounts.balance)
//                    + SUM({$currency}_margin_accounts.balance)
//                    + SUM(spot_{$currency}_accounts.balance)
//                    + SUM(airdrop_{$currency}_accounts.balance)) as balances,
//                    (SUM({$currency}_accounts.available_balance)
//                    + SUM({$currency}_margin_accounts.available_balance)
//                    + SUM(spot_{$currency}_accounts.available_balance)
//                    + SUM(airdrop_{$currency}_accounts.balance)) as available_balances")
//                    ->get();
//                continue;
//            }
                $result[$currency] = $query->selectRaw("(SUM({$currency}_accounts.balance)
                + SUM(spot_{$currency}_accounts.balance)) as balances,
            (SUM({$currency}_accounts.available_balance)
                + SUM(spot_{$currency}_accounts.available_balance)) as available_balances")
                    ->get();
                $balanceFutureOfCoin = collect($coinBalances)->first(function ($item) use ($currency) {
                        if (strtolower($item->asset) == $currency) {
                            return $item;
                        }
                        return null;
                    })?->totalBalance ?? 0;

                $result[$currency]['0']->balances = BigNumber::new($result[$currency]['0']->balances)->add($balanceFutureOfCoin)->toString();
            }

        }
        return $result;
    }

    public function getTotalUser()
    {
        return User::count();
    }

    public function getUsersForAdmin($params)
    {
        $today = Carbon::now(Consts::DEFAULT_TIMEZONE)->startOfDay();
        $activeTime = $today->timestamp * 1000;
        $expiredTime = $today->endOfDay()->timestamp * 1000;

        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        return User::leftJoin('users as referrers', 'users.referrer_id', 'referrers.id')
            ->leftJoin('user_fee_levels', function ($join) use ($activeTime, $expiredTime) {
                $join->on('users.id', '=', 'user_fee_levels.user_id');
                $join->where('user_fee_levels.active_time', '>=', $activeTime);
                $join->where('user_fee_levels.active_time', '<', $expiredTime);
            })
            ->with('securitySetting')
            ->when(array_key_exists('search_key', $params), function ($query) use ($params) {
                $searchKey = $params['search_key'];
                return $query->where(function ($q) use ($searchKey) {
                    $q->where('users.email', 'like', '%' . $searchKey . '%');
                    //->orWhere('users.name', 'like', '%' . $searchKey . '%')
                    //->orWhere('referrers.email', 'like', '%' . $searchKey . '%')
                    //->orWhere('users.real_account_no', 'like', '%' . $searchKey . '%');
                });
            })
            ->when(array_key_exists('sort', $params) && !empty($params['sort'] &&
                    array_key_exists('sort_type', $params) && !empty($params['sort_type'])),
                function ($query) use ($params) {
                    return $query->orderBy($params['sort'], $params['sort_type']);
                }, function ($query) {
                    return $query->orderBy('users.created_at', 'DESC');
                })
            ->when(array_key_exists('type', $params), function ($query) use ($params) {
                return $query->where('users.type', $params['type']);
            })
            ->when(array_key_exists('status', $params), function ($query) use ($params) {
                return $query->where('users.status', $params['status']);
            })
            ->when(array_key_exists('group', $params), function ($query) use ($params) {
                return $query->whereIn('users.id', function ($subQuery) use ($params) {
                    $subQuery->select('user_id')
                        ->from('user_group')
                        ->where('user_group.group_id', $params['group'])->get();
                });
            })
            ->when(array_key_exists('start_date', $params), function ($query) use ($params) {
//                $searchKey = Arr::get($params, 'search_key');
//                if ($searchKey) {
//                    // If exist search_key, disable search by Date time
//                    return $query;
//                }
                return $query->where('users.created_at', '>=', date("Y-m-d H:i:s", $params['start_date'] / 1000));
            })
            ->when(array_key_exists('end_date', $params), function ($query) use ($params) {
//                $searchKey = Arr::get($params, 'search_key');
//                if ($searchKey) {
//                    // If exist search_key, disable search by Date time
//                    return $query;
//                }
                return $query->where('users.created_at', '<=', date("Y-m-d H:i:s", $params['end_date'] / 1000));
            })
            ->select(
                'users.id',
                'users.created_at',
                'users.name',
                'users.referrer_code',
                'users.email',
                'users.status',
                'users.real_account_no',
                'users.type',
                'users.max_security_level',
                'referrers.email as referrer_email',
                'user_fee_levels.fee_level',
                'users.security_level',
                'users.account_note',
                'users.referrer_id',
                'users.memo',
                DB::raw('(select count(*) from user_group where user_group.user_id = users.id) as group_count'),
                DB::raw('(select group_concat(name separator \', \') as abc from user_group_setting where id in (
                            select group_id from user_group where user_id = users.id
                        )) as group_name')
            )
            ->paginate($limit);


        /**
         * return \App\User::leftJoin('user_device_registers as udr1', 'udr1.user_id', '=', 'users.id')
         * ->where('udr1.updated_at',
         * DB::raw("(select max(`updated_at`) FROM user_device_registers as udr2 WHERE udr1.user_id = udr2.user_id)"))
         * ->orWhereNull('udr1.updated_at')
         * ->select(
         * 'users.id', 'users.created_at', 'users.name', 'users.referrer_code', 'users.email',
         * 'users.status', 'users.real_account_no', 'users.type', 'users.max_security_level',
         * 'users.security_level', 'udr1.updated_at',
         * 'users.account_note', 'users.referrer_id')
         * ->orderBy('udr1.updated_at', 'ASC')
         * ->orderBy('users.email', 'ASC')
         * ->paginate($limit);
         */
    }

    public function getReferrers($params)
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        return User::where('type', Consts::USER_TYPE_REFERRER)
            ->where('id', '!=', $params['user_id'])
            ->when(array_key_exists('search_key', $params), function ($query) use ($params) {
                $searchKey = $params['search_key'];
                return $query->where(function ($q) use ($searchKey) {
                    $q->where('name', 'like', '%' . $searchKey . '%')
                        ->orWhere('email', 'like', '%' . $searchKey . '%');
                });
            })
            ->select('id', 'name', 'email')
            ->paginate($limit);
    }

    public function getAllReferrers()
    {
        return User::where('type', Consts::USER_TYPE_REFERRER)
            ->select('id', 'name', 'email')
            ->get();
    }

    public function getUserBalances($userId, $currencies, $onMaster = false, $store = null): array
    {
        $store = $store ?? Consts::TYPE_MAIN_BALANCE;
        $result = [];

        //        if ($store == Consts::TYPE_MAIN_BALANCE) {
        //            $service = new UserBalanceService();
        //            return $service->getBalanceTransactionMains($currencies, $userId);
        //        }

        foreach ($currencies as $currency) {
            $result[$currency] = $this->getDetailsUserBalance($userId, $currency, $onMaster, $store);
        }
        return $result;
    }

    public function getDetailsUserBalance($userId, $currency, $onMaster = false, $store = null)
    {
        $store = $store ?? Consts::TYPE_MAIN_BALANCE;

        $tableName = $this->getTableWithType($store, $currency);
        $table = $onMaster ? DB::connection('master')->table($tableName) : DB::table($tableName);
        $query = $table->select('balance', 'available_balance', 'usd_amount')->where('id', $userId);

        if ($store === Consts::TYPE_MAIN_BALANCE && $currency != Consts::CURRENCY_USD) {
            $query = $query->addSelect('blockchain_address');
        }

        if ($store === Consts::TYPE_MAIN_BALANCE && (Consts::CURRENCY_XRP == $currency || Consts::CURRENCY_EOS == $currency)) {
            $query = $query->addSelect('blockchain_sub_address');
        }

        return $query->first();
    }

    public function getDetailsUserUsdBalance($userId, $currency, $onMaster = false, $store = null): array
    {
        $tableMain = $this->getTableWithType(Consts::TYPE_MAIN_BALANCE, $currency);
        $tableExchange = $this->getTableWithType(Consts::TYPE_EXCHANGE_BALANCE, $currency);

        $table = $onMaster ? DB::connection('master')->table($tableMain) : DB::table($tableMain);
        $queryMain = $table->select('balance', 'available_balance')->where('id', $userId);

        $table = $onMaster ? DB::connection('master')->table($tableExchange) : DB::table($tableExchange);
        $queryExchange = $table->select('balance', 'available_balance')->where('id', $userId);

        // USD don't have margin table
        $queryMargin = [
            'balance' => 0,
            'available_balance' => 0,
        ];

        return ['main' => $queryMain->first(), 'exchange' => $queryExchange->first(), 'margin' => $queryMargin];
    }

    public function getDetailsUserSpotBalance($userId, $currency, $onMaster = false)
    {
        $store = Consts::TYPE_EXCHANGE_BALANCE;

        return $this->getDetailsUserBalance($userId, $currency, $onMaster, $store);
    }

    public function getFeeRate($userId, $feeType)
    {
        $userFeeLevel = $this->getUserFeeLevel($userId);
        $feeLevel = MasterdataService::getOneTable('fee_levels')
            ->filter(function ($value, $key) use ($userFeeLevel) {
                return $value->level == $userFeeLevel;
            })
            ->first();
        return $feeType === Consts::FEE_MAKER ? $feeLevel->fee_maker : $feeLevel->fee_taker;
    }

    private function getUserFeeLevel($userId)
    {
        $key = 'UserFeeLevel' . $userId;
        if (Cache::has($key)) {
            return Cache::get($key);
        }

        $today = Carbon::now(Consts::DEFAULT_TIMEZONE)->startOfDay();
        $activeTime = $today->timestamp * 1000;
        $userFeeLevel = UserFeeLevel::where('user_id', $userId)->where('active_time', $activeTime)->first();
        $level = 1;
        if ($userFeeLevel) {
            $level = $userFeeLevel->fee_level;
        }
        Cache::forever($key, $level);
        return $level;
    }

    public function getUserReferralFriends($userId, $params = array())
    {
        $userReferralFriends = DB::table('users')
            ->select('email', 'created_at')
            ->where('referrer_id', $userId)
            ->where('status', Consts::USER_ACTIVE)
            ->when(array_key_exists('limit', $params), function ($query) use ($params) {
                return $query->paginate($params['limit']);
            }, function ($query) {
                return $query->get();
            });
        if (array_key_exists('limit', $params)) {
            $userReferralFriends->getCollection()->transform(function ($obj) {
                $obj->email = $this->convertEmail($obj->email);
                return $obj;
            });
            return $userReferralFriends;
        } else {
            return $this->concealEmail($userReferralFriends, 'email');
        }
    }

    public function getAllReferrer($userId, $params = array())
    {
        $referrerIds = [];
        $setting = app(ReferralService::class)->getReferralSettings();
        $numberOfLevels = $setting->number_of_levels;
        for ($i = 1; $i <= $numberOfLevels; $i++) {
            $findAt = 'referrer_id_lv_' . $i;
            $referrers = MultiReferrerDetails::where($findAt, $userId)->pluck('user_id')->toArray();
            $referrerIds = array_merge($referrerIds, $referrers);
        }

        $userReferralFriends = User::select('email', 'created_at')
            ->whereIn('id', $referrerIds)
            ->orderBy('created_at', 'desc')
            ->when(array_key_exists('limit', $params), function ($query) use ($params) {
                return $query->paginate($params['limit']);
            }, function ($query) {
                return $query->get();
            });

        if (array_key_exists('limit', $params)) {
            $userReferralFriends->getCollection()->transform(function ($obj) {
                $obj->email = $this->convertEmail($obj->email);
                return $obj;
            });
            return $userReferralFriends;
        } else {
            return $this->concealEmail($userReferralFriends, 'email');
        }
    }

    public function getUserReferralCommission($userId, $params = array())
    {
        // type is enum spot, coin_m, usd_m
        $type = @$params['type'] ?? 'spot';
        $userReferralCommissions = DB::table('referrer_histories')
            ->select('created_at', 'coin', 'transaction_owner_email as email', 'amount', 'commission_rate', 'type',
                'symbol', 'asset_future')
            ->where('user_id', $userId)
            ->when(!empty($type), function ($query) use ($type) {
                if ($type === 'spot' || $type === 'future') {
                    return $query->where('type', $type);
                } else {
                    $lstUSDM = ['USD', 'USDT'];
                    if ($type === 'usd_m') {
                        return $query->whereIn('coin', $lstUSDM)->where('type', 'future');
                    } else {
                        return $query->whereNotIn('coin', $lstUSDM)->where('type', 'future');
                    }
                }
            })
            ->when(array_key_exists('startDate', $params), function ($query) use ($params) {
                return $query->where('created_at', '>=', $params['startDate']);
            })
            ->when(array_key_exists('endDate', $params), function ($query) use ($params) {
                return $query->where('created_at', '<=', $params['endDate']);
            })
            ->orderByRaw("created_at desc, cast(amount as decimal(8,8)) desc")
            ->when(array_key_exists('limit', $params), function ($query) use ($params) {
                return $query->paginate($params['limit']);
            }, function ($query) {
                return $query->get();
            });

        if (array_key_exists('limit', $params)) {
            $userReferralCommissions->getCollection()->transform(function ($obj) {
                $obj->email = $this->convertEmail($obj->email);
                return $obj;
            });
            return $userReferralCommissions;
        } else {
            return $this->concealEmail($userReferralCommissions, 'email');
        }
    }

    public function getTopUserReferralCommission()
    {
        $key = "TopUserReferralCommission";
        $data = Cache::get($key);
        if ($data) {
            return $data;
        }
        $topRefUserCommissions = UserTransaction::select('email', DB::raw('SUM(commission_btc) AS totalCommissionBtc'))
            ->where('type', Consts::USER_TRANSACTION_TYPE_COMMISSION)
            ->groupBy('email')
            ->orderBy('totalCommissionBtc', 'desc')
            ->take(UserService::USER_REFERRAL_COMMISSION_LIMIT)
            ->get();
        $result = $this->concealEmail($topRefUserCommissions, 'email');
        Cache::put($key, $result, UserService::CACHE_LIVE_TIME);
        return $result;
    }

    private function concealEmail($data, $field)
    {
        return $data->each(function ($item) use ($field) {
            $item->{$field} = $this->convertEmail($item->{$field});
        });
    }

    private function convertEmail($email): string
    {
        $posOfDot = strrpos($email, '.');
        $length = strlen($email);
        return substr($email, 0, 2) . '***@***' . substr($email, $posOfDot, $length - $posOfDot);
    }

    public function getEmailByUserId($userId)
    {
        return User::find($userId)->email;
    }

    public static function createUserQrcode($userId, $url)
    {
        $UserQrcode = DB::table('users')->select('referrer_code')
            ->where('id', $userId)
            ->first();
        $url = $url . $UserQrcode->referrer_code;
        $UserQrcode->urlImg = Utils::makeQrCodeReferral($url);
        $UserQrcode->url = $url;
        return $UserQrcode;
    }

    public function getOrderBookSettings($userId, $currency, $coin)
    {
        $settings = DB::table('user_order_book_settings')
            ->where('user_id', $userId)
            ->where('currency', $currency)
            ->where('coin', $coin)
            ->first();
        return $settings ?: (object)Consts::DEFAULT_ORDER_BOOK_SETTINGS;
    }

    public function createOrUpdateOrderBookSettings($userId, $currency, $coin, $settings)
    {
        $query = DB::table('user_order_book_settings')
            ->where('user_id', $userId)
            ->where('currency', $currency)
            ->where('coin', $coin);
        if ((clone $query)->count() > 0) {
            (clone $query)->update($settings);
        } else {
            $conditions = [
                'user_id' => $userId,
                'currency' => $currency,
                'coin' => $coin
            ];
            $settings = array_merge(Consts::DEFAULT_ORDER_BOOK_SETTINGS, $settings, $conditions);
            (clone $query)->insert($settings);
        }
        // update notification settings for all pairs, these settings should be moved to other table
        $shareSettings = [];
        $keys = ['notification', 'notification_created', 'notification_matched', 'notification_canceled'];
        foreach ($keys as $key) {
            if (array_key_exists($key, $settings)) {
                $shareSettings[$key] = $settings[$key];
            }
        }
        if (count($shareSettings)) {
            DB::table('user_order_book_settings')
                ->where('user_id', $userId)
                ->update($shareSettings);
        }
        // end update notification settings
        return $this->getOrderBookSettings($userId, $currency, $coin);
    }

    public function getOrderBookPriceGroup($userId, $currency, $coin)
    {
        $settings = $this->getOrderBookSettings($userId, $currency, $coin);
        $priceGroups = MasterdataService::getOneTable('price_groups')
            ->filter(function ($value, $key) use ($settings, $currency, $coin) {
                return $value->group == $settings->price_group && $value->currency == $currency && $value->coin == $coin;
            })
            ->first();
        return $priceGroups->value;
    }

    public function getUserIdFromAddress($address, $currency, $networkId)
    {
        //        $currency = $this->updateCurrencyIfNeed($currency);

        //$currency = FormatFa::getPlatformCurrency($currency);

        return DB::table('user_blockchain_addresses')
            ->where('currency', $currency)
            ->where('network_id', $networkId)
            ->where('blockchain_address', $address)
            ->value('user_id');
    }

    public function storeSecret($secret, $userId)
    {
        return DB::table('users')
            ->where('id', $userId)
            ->update(['google_authentication' => $secret]);
    }

    public function getCurrentUserLocale(): string
    {
        if (!Auth::check()) {
            return Consts::DEFAULT_USER_LOCALE;
        }
        return $this->getUserLocale(Auth::id());
    }

    public function getCurrentAdminLocale(): string
    {
        if (!Auth::guard('admin')->check()) {
            return Consts::DEFAULT_USER_LOCALE;
        }
        return Auth::guard('admin')->user()->locale;
    }

    public function sendNotifyFirebase(int $userId, array $params): void
    {
        $user = User::query()->find($userId);
        $locale = $user->getLocale();
        FirebaseNotificationService::send($user->id, __('title.notification.add_whitelist', [], $locale),
            __('body.notification.add_whitelist',
                ['asset' => $params['coin'], 'address' => $params['wallet_address'], 'time' => Carbon::now()]));
    }

    public function insertWalletAddress($params)
    {
        $dataSet = [
            'user_id' => Auth::id(),
            'coin' => $params['coin'],
            'network_id' => $params['network_id'],
            'wallet_name' => $params['name'],
            'wallet_address' => $params['wallet_address'],
            'is_whitelist' => $params['white_list'],
            'wallet_sub_address' => $params['wallet_sub_address'],
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ];
        $userWithdrawAddress = UserWithdrawalAddress::insert($dataSet);
//        $this->sendNotifyFirebase(Auth::id(), $params);

        return $userWithdrawAddress;
    }

    public function updateWalletsWhiteList($idWallets, $active)
    {
        return UserWithdrawalAddress::my()
            ->whereIn('id', $idWallets)
            ->update(['is_whitelist' => $active]);
    }

    public function removeWalletsAddress($idWallets)
    {
        $query = UserWithdrawalAddress::my();

        if (is_array($idWallets)) {
            return $query->whereIn('id', $idWallets)->delete();
        }

        return $query->where('id', $idWallets)->delete();
    }

    public function getUserLocale($userId): string
    {
        $userLocale = UserSetting::where('user_id', $userId)
            ->where('key', 'locale')
            ->value('value');

        if ($userLocale) {
            return $userLocale;
        }

        return Consts::DEFAULT_USER_LOCALE;
    }

    public function updateOrCreateDeviceToken($deviceToken): string
    {
        UserSetting::query()->where([
            ['user_id', Auth::id()],
            ['key', 'device_token'],
            ['value', $deviceToken]
        ])->update(['value' => null]);

        UserSetting::query()->updateOrCreate(
            ['user_id' => Auth::id(), 'key' => 'device_token'],
            ['value' => $deviceToken]
        );
        $topic = Consts::TOPIC_PRODUCER_DEVICE_TOKEN;
        $data = [
            'userId' => Auth::id(),
            'deviceToken' => $deviceToken
        ];
        Utils::kafkaProducer($topic, $data);

        return $deviceToken;
    }

    public function updateOrCreateUserLocale($locale)
    {
        if (!Auth::check()) {
            return $locale;
        }

        UserSetting::updateOrCreate(
            ['user_id' => Auth::id(), 'key' => 'locale'],
            ['value' => $locale]
        );

        $topic = Consts::TOPIC_PRODUCER_LOCALE;
        $data = [
            "id" => Auth::id(),
            "location" => $locale
        ];
        Utils::kafkaProducer($topic, $data);

        return $locale;
    }

    public function updateOrCreateUserLocaleWhenLogin($locale, $userId)
    {
        UserSetting::updateOrCreate(
            ['user_id' => $userId, 'key' => 'locale'],
            ['value' => @$locale ?? "en"]
        );

        return $locale;
    }

    public function updateOrCreateAdminLocale($locale)
    {
        if (!Auth::guard('admin')->check()) {
            return $locale;
        }
        $admin = Auth::guard('admin')->user();
        $admin->locale = $locale;
        $admin->save();

        return $locale;
    }

    public function createDepositAddress($currency, $networkId)
    {
        DB::connection('master')->beginTransaction();
        try {
            $result = app(DepositService::class)->create($currency, $networkId);
            DB::connection('master')->commit();
            return $result;
        } catch (Exception $e) {
            DB::connection('master')->rollBack();
            throw $e;
        }
    }

    private function updateCurrencyIfNeed($currency)
    {
        switch ($currency) {
            case Consts::CURRENCY_AMAL:
                return Consts::CURRENCY_ETH;
            default:
                return $currency;
        }
    }

    public function getDepositAddress($currency, $networkId, $onMaster = false)
    {
        $result = CheckFa::deposit($currency, $networkId);
        if ($result === 0) {
            throw new HttpException(422, trans('exception.is_deposit'));
        }
       /* $table = $onMaster ? DB::connection('master')->table($currency . '_accounts') : DB::table($currency . '_accounts');
        $query = $table->where('id', Auth::id());
        if ($currency != Consts::CURRENCY_USD) {
            $query = $query->addSelect('blockchain_address');
        }
        if (Consts::CURRENCY_XRP == $currency || Consts::CURRENCY_EOS == $currency || Consts::CURRENCY_TRX == $currency) {
            $query = $query->addSelect('blockchain_sub_address');
        }
        $address = $query->first();
        if (empty($address)) {
            throw new HttpException(422, __('exception.row_not_found', [compact('table')]));
        }
        if ($address->blockchain_address) {
            return (array)$address;
        }*/
        return $this->createDepositAddress($currency, $networkId);
    }

    /**
     * @throws Exception
     */
    public function createAddressQr($currency, $networkId)
    {
        DB::connection('master')->beginTransaction();

        try {
            $result = $this->getDepositAddress($currency, $networkId);
            DB::connection('master')->commit();

            if ($result) {
                // get QR Code
                if (!empty($result['blockchain_address'])) {
                    $result['qrcode'] = Utils::makeQrCode($result['blockchain_address']);
                }
                if (!empty($result['blockchain_sub_address'])) {
                    $result['qr_tag'] = Utils::makeQrCode($result['blockchain_sub_address']);
                }
            }

            return $result;
        } catch (Exception $e) {
            DB::connection('master')->rollBack();
            throw $e;
        }
    }

    public function getAddressNetworks($currency) {
        return DB::table('coins', 'c')
            ->join('network_coins as nc', 'c.id', 'nc.coin_id')
            ->join('networks as n', 'nc.network_id', 'n.id')
            ->where([
                'c.coin' => $currency,
                'n.enable' => true,
                'nc.network_enable' => true,
                'n.network_deposit_enable' => true,
                'nc.network_deposit_enable' => true,
            ])
            ->selectRaw('n.*')
            ->get();

    }

    public function getWithdrawalNetworks($currency) {
        return DB::table('coins', 'c')
            ->join('network_coins as nc', 'c.id', 'nc.coin_id')
            ->join('networks as n', 'nc.network_id', 'n.id')
            ->where([
                'c.coin' => $currency,
                'n.enable' => true,
                'nc.network_enable' => true,
                'n.network_withdraw_enable' => true,
                'nc.network_withdraw_enable' => true,
            ])
            ->selectRaw('n.*, nc.withdraw_fee as fee, nc.min_withdraw as minium_withdrawal')
            ->get();
    }

    public function getUsers($params)
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        $searchKey = Arr::get($params, 'search_key', null);
        $sort = Arr::get($params, 'sort', null);
        $sortType = Arr::get($params, 'sort_type', null);

        $query = User::leftJoin('users as users2', 'users2.id', 'users.referrer_id');
        if ($searchKey) {
            $query = $query->where(function ($q) use ($searchKey) {
                $q->where('users.name', 'like', '%' . $searchKey . '%')
                    ->orWhere('users.email', 'like', '%' . $searchKey . '%')
                    ->orWhere('users2.name', 'like', '%' . $searchKey . '%');
            });
        }

        if ($sort) {
            $sortType = ($sortType) ? $sortType : 'desc';
            $query = $query->orderBy($sort, $sortType);
        }

        return $query->select('users.id', 'users.name', 'users.email', 'users2.name as referrer_name',
            'users.type')->paginate($limit);
    }

    public function getUserAccessHistories($userId, $params = [])
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        $query = UserConnectionHistory::orderBy('created_at', 'desc')->where('user_id', $userId)->with('device');
        if (array_key_exists('start_date', $params) && (!empty($params['start_date']))) {
            $query = $query->whereDate('created_at', '>=', $params['start_date']);
        }

        if (array_key_exists('end_date', $params) && (!empty($params['end_date']))) {
            $query = $query->whereDate('created_at', '<=', $params['end_date']);
        }
        return $query->paginate($limit);
    }

    public function getReferrerFee($params)
    {
        $startDate = $params['start_date'];
        $endDate = $params['end_date'];
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        $searchKey = Arr::get($params, 'search_key', '');

        $users = User::where('users.type', 'referrer')
            ->select('id', 'email', 'name')
            ->with('referrered_users');
        if ($searchKey) {
            $users = $users->where(function ($query) use ($searchKey) {
                $query->where('users.email', 'like', "%{$searchKey}%")
                    ->orWhere('users.name', 'like', "%{$searchKey}%");
            });
        }
        $users = $users->paginate($limit);

        $referreredUserIds = $users->getCollection()->pluck('referrered_users')->flatten()->pluck('id');

        $transactionFee = TransactionService::getReferredFee($referreredUserIds, $startDate, $endDate);
        $buyOrderFee = OrderService::getBuyerReferredFee($referreredUserIds, $startDate, $endDate);
        $sellOrderFee = OrderService::getSellerReferredFee($referreredUserIds, $startDate, $endDate);

        return $users->getCollection()->each(function ($item) use ($transactionFee, $buyOrderFee, $sellOrderFee) {
            $referreredUserIds = $item->referrered_users->pluck('id');
            unset($item->referrered_users);
            $coins = MasterdataService::getCurrenciesAndCoins();
            foreach ($referreredUserIds as $userId) {
                foreach ($coins as $coin) {
                    if (!$item["{$coin}_fee"]) {
                        $item["{$coin}_fee"] = 0;
                    }
                    $item["{$coin}_fee"] = BigNumber::new($this->getFee($userId, $coin,
                        $transactionFee))->add($this->getFee($userId, $coin, $buyOrderFee))->add($this->getFee($userId,
                        $coin, $sellOrderFee))->add($item["{$coin}_fee"])->toString();
                }
            }
        });
    }

    private function getFee($userId, $coin, $feeList)
    {
        if (isset($feeList[$userId])) {
            return $feeList[$userId]["{$coin}_fee"];
        }
        return 0;
    }

    public function getUserKycs($params)
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        return KYC::select('user_kyc.id', 'users.email', 'user_kyc.full_name', 'user_kyc.country', 'user_kyc.id_number',
            'user_kyc.id_front', 'user_kyc.id_back', 'user_kyc.id_selfie', 'user_kyc.created_at', 'user_kyc.status')
            ->join('users', 'user_kyc.user_id', '=', 'users.id')
            ->when(!empty($params['search_key']), function ($query) use ($params) {
                $searchKey = $params['search_key'];
                $query->where(function ($q) use ($searchKey) {
                    $q->where('full_name', 'like', '%' . $searchKey . '%')
                        ->orWhere('country', 'like', '%' . $searchKey . '%')
                        ->orWhere('id_number', 'like', '%' . $searchKey . '%')
                        ->orWhere('email', 'like', '%' . $searchKey . '%');
                });
            })
            ->orderBy('status')
            ->when(
                !empty($params['sort']) && !empty($params['sort_type']),
                function ($query) use ($params) {
                    $query->orderBy($params['sort'], $params['sort_type']);
                },
                function ($query) use ($params) {
                    $query->orderBy('created_at', 'desc');
                }
            )
            ->paginate($limit);
    }

    public function getDetailUserKyc($params)
    {
        $userKyc = KYC::select('user_kyc.id', 'users.email', 'user_kyc.full_name', 'user_kyc.country',
            'user_kyc.id_number', 'user_kyc.status', 'user_kyc.gender', 'user_kyc.id_front', 'user_kyc.id_back',
            'user_kyc.id_selfie', 'user_kyc.created_at')->join('users', 'user_kyc.user_id', '=', 'users.id')
            ->where('user_kyc.id', $params['kyc_id'])
            ->first();
        if ($userKyc) {
            $userKyc->id_front = Utils::getPresignedUrl($userKyc->id_front);
            $userKyc->id_back = Utils::getPresignedUrl($userKyc->id_back);
            $userKyc->id_selfie = Utils::getPresignedUrl($userKyc->id_selfie);
        }
        return $userKyc;
    }

    public function updateUserSecurityLevel($userId = null)
    {
        $securitySetting = UserSecuritySetting::where('id', $userId)->first();

        $user = User::where('id', $userId)->first();

        $user->security_level = Consts::SECURITY_LEVEL_OTP;

        if ($user->max_security_level > Consts::SECURITY_LEVEL_OTP) {
            $user->security_level = $user->max_security_level;
        }

		if (!$securitySetting->otp_verified) {
			$user->security_level = Consts::SECURITY_LEVEL_IDENTITY;
		}

        if (!$securitySetting->identity_verified) {
            $user->security_level = Consts::SECURITY_LEVEL_EMAIL;
        }

        return $user->save();
    }

    public function getUserLoginHistory($params)
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        return UserConnectionHistory::orderBy('created_at', 'desc')
            ->where('user_id', $params)->with('device')->paginate($limit);
    }

    public function getDeviceRegister($userId)
    {
        if (!$userId) {
            throw new HttpException(422, __('exception.user_not_found'));
        }
        $user = User::findOrFail($userId);
        return $user->devices->load('userConnectionHistories');
    }

    public function deleteDevice($userId, $deviceId)
    {
        return UserDeviceRegister::where('user_id', $userId)
            ->where('id', $deviceId)
            ->delete();
    }

    public function disableOrEnableUser($user, $newPassword = null, $token = null): void
    {
        if ($user->status === Consts::USER_ACTIVE) {
            DB::table('password_resets')->insert([
                'email' => $user->email,
                'token' => $token,
                'created_at' => Carbon::now(),
            ]);

            $user->notify(new ResetPassword($token));
            return;
        }
        $orderService = new OrderService();

        // Cancel al orders which are pending or stopping or executing.
        $orders = $orderService->getOrderPendingWithoutPaginate($user->id)->get();
        $orderService->cancelOrders($orders);

        $user->notify(new BanAccount($user->status));
    }

    public function convertSmallBalance($smallCoins = [], $baseCoin = Consts::CURRENCY_AMAL): array
    {
        $userId = request()->user()->id;
        $baseCoinRecord = DB::table($baseCoin . '_accounts')->where('id', $userId)->first();
        if (!$baseCoinRecord) {
            throw new HttpException(422, __('exception.table_not_found', ['table' => $baseCoin . '_accounts']));
        }

        $priceService = new PriceService;
        $totalPlusCoin = new BigNumber(0);

        foreach ($smallCoins as $coin) {
            if ($coin == $baseCoin) {
                continue;
            }

            $coinRecord = DB::table($coin . '_accounts')
                ->select('balance', 'available_balance')
                ->where('id', $userId)
                ->where('available_balance', '>', 0)
                ->first();

            if (!$coinRecord) {
                continue;
            }

            if (!$this->checkAvailableBalanceBeforeConvert($coinRecord, $coin, $priceService)) {
                continue;
            }
            $coinRecord = $this->updateCoinRecord($userId, $coin);
            if (!$coinRecord) {
                continue;
            }

            // Update total plus coin for BaseCoin table
            $plusCoin = $priceService->convertAmount($coinRecord->available_balance, $coin, $baseCoin);
            $totalPlusCoin = $totalPlusCoin->add($plusCoin);
        }
        $this->updateBaseCoinRecord($userId, $baseCoin, $totalPlusCoin);
        $rs['status'] = true;
        $rs['total'] = $totalPlusCoin->toString();
        return $rs;
    }

    private function checkAvailableBalanceBeforeConvert($coinRecord, $coin, $priceService): bool
    {
        $plusCoinCheck = $priceService->convertPriceToBTC($coin, true);
        $plusCoinCheck = BigNumber::new($coinRecord->available_balance)->mul($plusCoinCheck)->toString();
        if (BigNumber::new($plusCoinCheck)->comp(Consts::MIN_SMALL_AVAILABLE_BALANCE) > 0) {
            return false;
        }
        return true;
    }

    private function updateCoinRecord($userId, $coin)
    {
        $priceService = new PriceService;
        // Lock record with id to update
        $coinRecord = DB::table($coin . '_accounts')->where('id', $userId)->lockForUpdate()->first();

        // Double check available_balance: 0 < available_balance < Consts::MIN_SMALL_AVAILABLE_BALANCE
        $avaiableBalance = $coinRecord->available_balance;
        if ($avaiableBalance <= 0 || !$this->checkAvailableBalanceBeforeConvert($coinRecord, $coin, $priceService)) {
            return null;
        }
        // Calculate Current Coin Record
        $coinNewBalance = BigNumber::new($coinRecord->balance)->sub($avaiableBalance);

        DB::table($coin . '_accounts')
            ->where('id', $userId)
            ->update([
                'available_balance' => 0,
                'balance' => $coinNewBalance->toString(), // Sub Balance
                'usd_amount' => $priceService->toUsdAmount($coin, $coinNewBalance),
            ]);

        return $coinRecord;
    }

    private function updateBaseCoinRecord($userId, $baseCoin, $totalPlusCoin)
    {
        $priceService = new PriceService;
        // Calculate AML Record
        // Lock record with id to update
        $baseCoinRecord = DB::table($baseCoin . '_accounts')
            ->select('balance', 'available_balance')
            ->where('id', $userId)
            ->lockForUpdate()
            ->first();

        $baseCoinNewBalance = BigNumber::new($baseCoinRecord->balance)->add($totalPlusCoin);
        $baseCoinNewAvailableBalance = BigNumber::new($baseCoinRecord->available_balance)->add($totalPlusCoin);

        DB::table($baseCoin . '_accounts')
            ->where('id', $userId)
            ->update([
                'available_balance' => $baseCoinNewAvailableBalance->toString(), // Plus Available Balance
                'balance' => $baseCoinNewBalance->toString(), // Plus Balance
                'usd_amount' => $priceService->toUsdAmount($baseCoin, $baseCoinNewBalance),
            ]);
    }

    protected function getTableWithType($balanceType, $coinType): string
    {
		$coinType = strtolower($coinType);
        // Exchange Table
        if ($balanceType == Consts::TYPE_EXCHANGE_BALANCE) {
            return 'spot_' . $coinType . '_accounts';
        }

//        // BTC Margin Table
//        if ($balanceType == Consts::TYPE_MARGIN_BALANCE && $coinType == Consts::CURRENCY_BTC) {
//            return 'margin_accounts';
//        }
//
//        // AMAL Margin Table
//        if ($balanceType == Consts::TYPE_MARGIN_BALANCE && $coinType == Consts::CURRENCY_AMAL) {
//            return $coinType . '_margin_accounts';
//        }
//
//        // MAM Table
//        if ($balanceType == Consts::TYPE_MAM_BALANCE) {
//            return 'mam_' . $coinType . '_accounts';
//        }
//
//        //Airdrop Table
//        if ($balanceType == Consts::TYPE_AIRDROP_BALANCE) {
//            return 'airdrop_' . $coinType . '_accounts';
//        }

        // Main Table
        return $coinType . '_accounts';
    }

    public function sendBalanceEvents($userId, $coinType, $fromBalance, $toBalance): void
    {
        SendBalance::dispatchIfNeed($userId, [$coinType], $fromBalance);
        SendBalance::dispatchIfNeed($userId, [$coinType], $toBalance);

        //$balances = $this->getUserAccounts($userId);
        //event(new BalanceUpdated($userId, $balances));

        //SendBalance::dispatchIfNeed($userId, [$coinType]);
    }

    public function transferBalance($coinValue, $coinType, $fromBalance, $toBalance, $userId)
    {
        //        event(new TransferEvent($userId, $coinType, $coinValue, $fromBalance, $toBalance));
        if ($coinType == Consts::CURRENCY_BTC && ($fromBalance == Consts::TYPE_MARGIN_BALANCE || $toBalance == Consts::TYPE_MARGIN_BALANCE)) {
            $fromTable = $this->getTableWithType($fromBalance, $coinType);
            $toTable = $this->getTableWithType($toBalance, $coinType);

            $fromData = DB::table($fromTable)
                ->where($fromBalance == Consts::TYPE_MARGIN_BALANCE ? 'owner_id' : 'id', $userId)
                ->first();
            $fromData = DB::table($fromTable)->lockForUpdate()->find($fromData->id);

            $toData = DB::table($toTable)
                ->where($toBalance == Consts::TYPE_MARGIN_BALANCE ? 'owner_id' : 'id', $userId)
                ->first();
            $toData = DB::table($toTable)->lockForUpdate()->find($toData->id);

            // Check balance is sufficient
            if (
                BigNumber::new($fromData->balance)->comp(0) <= 0 ||
                BigNumber::new($fromData->balance)->comp($coinValue) < 0    // $fromData->balance < $coinValue
            ) {
                throw new \Exception(__('messages.error.balance_insufficient'));
            }

            // Check available balance is sufficient
            if (
                $fromBalance != Consts::TYPE_MARGIN_BALANCE && (BigNumber::new($fromData->available_balance)->comp(0) <= 0 ||
                    BigNumber::new($fromData->available_balance)->comp($coinValue) < 0)  // $fromData->available_balance < $coinValue
            ) {
                throw new \Exception(__('messages.error.' . $fromBalance . '.available_balance_insufficient'));
            }

            $resultFrom = DB::table($fromTable)->where('id', $fromData->id)
                ->update([
                    'balance' => BigNumber::new($fromData->balance)->sub($coinValue),
                    'available_balance' => $fromBalance == Consts::TYPE_MARGIN_BALANCE ? BigNumber::new($fromData->available_balance) : BigNumber::new($fromData->available_balance)->sub($coinValue),
                ]);

            $resultTo = DB::table($toTable)->where('id', $toData->id)
                ->update([
                    'balance' => BigNumber::new($toData->balance)->add($coinValue),
                    'available_balance' => $toBalance == Consts::TYPE_MARGIN_BALANCE ? BigNumber::new($toData->available_balance) : BigNumber::new($toData->available_balance)->add($coinValue),
                ]);
        } elseif ($coinType == Consts::CURRENCY_AMAL && ($fromBalance == Consts::TYPE_MARGIN_BALANCE || $toBalance == Consts::TYPE_MARGIN_BALANCE)) {
            $fromTable = $this->getTableWithType($fromBalance, $coinType);
            $toTable = $this->getTableWithType($toBalance, $coinType);

            $fromData = DB::table($fromTable)
                ->where($fromBalance == Consts::TYPE_MARGIN_BALANCE ? 'owner_id' : 'id', $userId)
                ->first();
            $fromData = DB::table($fromTable)->lockForUpdate()->find($fromData->id);

            $toData = DB::table($toTable)
                ->where($toBalance == Consts::TYPE_MARGIN_BALANCE ? 'owner_id' : 'id', $userId)
                ->first();
            $toData = DB::table($toTable)->lockForUpdate()->find($toData->id);

            // Check balance is sufficient
            if (
                BigNumber::new($fromData->balance)->comp(0) <= 0 ||
                BigNumber::new($fromData->balance)->comp($coinValue) < 0    // $fromData->balance < $coinValue
            ) {
                throw new \Exception(__('messages.error.balance_insufficient'));
            }

            // Check available balance is sufficient
            if (
                BigNumber::new($fromData->available_balance)->comp(0) <= 0 ||
                BigNumber::new($fromData->available_balance)->comp($coinValue) < 0  // $fromData->available_balance < $coinValue
            ) {
                throw new \Exception(__('messages.error.' . $fromBalance . '.available_balance_insufficient'));
            }

            $resultFrom = DB::table($fromTable)->where($fromBalance == Consts::TYPE_MARGIN_BALANCE ? 'owner_id' : 'id',
                $userId)
                ->update([
                    'balance' => BigNumber::new($fromData->balance)->sub($coinValue),
                    'available_balance' => BigNumber::new($fromData->available_balance)->sub($coinValue),
                ]);

            $resultTo = DB::table($toTable)->where($toBalance == Consts::TYPE_MARGIN_BALANCE ? 'owner_id' : 'id',
                $userId)
                ->update([
                    'balance' => BigNumber::new($toData->balance)->add($coinValue),
                    'available_balance' => BigNumber::new($toData->available_balance)->add($coinValue),
                ]);
        } else {
            $fromTable = $this->getTableWithType($fromBalance, $coinType);
            $toTable = $this->getTableWithType($toBalance, $coinType);

            $fromData = DB::table($fromTable)->where('id', $userId)->lockForUpdate()->first();
            $toData = DB::table($toTable)->where('id', $userId)->lockForUpdate()->first();

            // Check balance is sufficient
            if (
                BigNumber::new($fromData->balance)->comp(0) <= 0 ||
                BigNumber::new($fromData->balance)->comp($coinValue) < 0    // $fromData->balance < $coinValue
            ) {
                throw new \Exception(__('messages.error.balance_insufficient'));
            }

            // Check available balance is sufficient
            if (
                BigNumber::new($fromData->available_balance)->comp(0) <= 0 ||
                BigNumber::new($fromData->available_balance)->comp($coinValue) < 0  // $fromData->available_balance < $coinValue
            ) {
                throw new \Exception(__('messages.error.' . $fromBalance . '.available_balance_insufficient'));
            }

            $resultFrom = DB::table($fromTable)->where('id', $userId)
                ->update([
                    'balance' => BigNumber::new($fromData->balance)->sub($coinValue),
                    'available_balance' => BigNumber::new($fromData->available_balance)->sub($coinValue),
                ]);

            $resultTo = DB::table($toTable)->where('id', $userId)
                ->update([
                    'balance' => BigNumber::new($toData->balance)->add($coinValue),
                    'available_balance' => BigNumber::new($toData->available_balance)->add($coinValue),
                ]);
        }

        if ($resultFrom && $resultTo) {
            $this->sendBalanceEvents($userId, $coinType, $fromBalance, $toBalance);
            return TransferHistory::create([
                'user_id' => $userId,
                'email' => @User::find($userId)->email ?? '',
                'coin' => $coinType,
                'source' => $fromBalance,
                'destination' => $toBalance,
                'amount' => $coinValue
            ]);
        }

        return false;
    }

    public function transferBalanceFromMainToAirdrop(
        $coinValue,
        $coinType,
        $fromBalance,
        $toBalance,
        $userId,
        $type = null
    ): bool {
        if ($coinType != Consts::CURRENCY_AMAL) {
            return false;
        }
        $fromTable = $this->getTableWithType($fromBalance, $coinType);
        $toTable = $this->getTableWithType($toBalance, $coinType);

        $fromData = DB::table($fromTable)->where('id', $userId)->lockForUpdate()->first();
        $toData = DB::table($toTable)->where('id', $userId)->lockForUpdate()->first();

        if (
            BigNumber::new($fromData->balance)->comp(0) <= 0 ||
            BigNumber::new($fromData->balance)->comp($coinValue) < 0    // $fromData->balance < $coinValue
        ) {
            throw new \Exception(__('messages.error.balance_insufficient'));
        }

        // Check available balance is sufficient
        if (
            BigNumber::new($fromData->available_balance)->comp(0) <= 0 ||
            BigNumber::new($fromData->available_balance)->comp($coinValue) < 0  // $fromData->available_balance < $coinValue
        ) {
            throw new \Exception(__('messages.error.' . $fromBalance . '.available_balance_insufficient'));
        }

        $resultFrom = DB::table($fromTable)->where('id', $userId)
            ->update([
                'balance' => BigNumber::new($fromData->balance)->sub($coinValue),
                'available_balance' => BigNumber::new($fromData->available_balance)->sub($coinValue),
            ]);

        $resultTo = DB::table($toTable)->where('id', $userId)
            ->update([
                'balance' => BigNumber::new($toData->balance)->add($coinValue),
            ]);

        if ($resultFrom && $resultTo) {
            $enableTypeSpecial = config('airdrop.enable_special_type_unlock');
            if ($type == Consts::AIRDROP_TYPE_SPECIAL || $enableTypeSpecial == 1) {
                $this->createHistoryLockAirdropRecord($userId, $coinValue, 1);
            } else {
                $this->createHistoryLockAirdropRecord($userId, $coinValue, 0);
            }
            $this->sendBalanceEvents($userId, $coinType, $fromBalance, $toBalance);

            TransferHistory::create([
                'user_id' => $userId,
                'email' => @User::find($userId)->email ?? '',
                'coin' => $coinType,
                'source' => $fromBalance,
                'destination' => $toBalance,
                'amount' => $coinValue
            ]);

            return true;
        }

        return false;
    }

    public function createHistoryLockAirdropRecord($userId, $totalBalance, $enableTypeSpecial)
    {
        $user = User::find($userId);
        $data = [
            'user_id' => $userId,
            'email' => $user->email,
            'status' => Consts::AIRDROP_UNLOCKING,
            'total_balance' => $totalBalance,
            'amount' => 0,
            'type' => '',
            'unlocked_balance' => 0,
            'last_unlocked_date' => Carbon::now()->toDateString()
        ];
        // if($enableTypeSpecial) {
        //     $data['type'] = Consts::AIRDROP_TYPE_SPECIAL;
        // }
        return AirdropHistoryLockBalance::create($data);
    }

    public function createHistoryLockAirdropRecordPerpetual($userId, $totalBalance)
    {
        $user = User::find($userId);
        $data = [
            'user_id' => $userId,
            'email' => $user->email,
            'status' => Consts::AIRDROP_UNLOCKING,
            'total_balance' => $totalBalance,
            'amount' => 0,
            'unlocked_balance' => 0,
            'last_unlocked_date' => Carbon::now()->toDateString(),
            'type' => Consts::AIRDROP_TYPE_SPECIAL
        ];
        return AirdropHistoryLockBalance::create($data);
    }

    public function getCustomerInformation($userId)
    {
        return User::with('userInfo')->find($userId)->first();
    }

    public function updateFakeName($fakeName): bool
    {
        $user = Auth::user();
        $user->fake_name = $fakeName;
        $user->save();
        return true;
    }

    public function addBiometrics($request): bool
    {
        $user = request()->user();
        $method = $request->method;
        $pubKey = $request->signature;
        if ($pubKey && $method) {
            $user->$method = $pubKey;
            if ($method == 'faceID') {
                $user->fingerprint = null;
            } else {
                $user->faceID = null;
            }
            $user->save();
            return true;
        }

        return false;
    }

    public function updateAntiPhishing($request)
    {
        DB::beginTransaction();
        try {
            $user = request()->user();
            $userAntiPhishing = UserAntiPhishing::where([
                'is_active' => true,
                'user_id' => $user->id,
            ])->first();
            $code = $this->genCodeAntiPhising($user, $request['anti_phishing_code']);
            $userHasCode = UserAntiPhishing::where([
                'user_id' => $user->id,
                'anti_phishing_code' => $request['anti_phishing_code']
            ])->first();
            if ($userAntiPhishing) {
                if ($userAntiPhishing->anti_phishing_code != $request['anti_phishing_code']) {
                    Mail::queue(new MailVerifyAntiPhishing($user, $code, 'update'));
                    if (!$userHasCode) {
                        $data = [
                            'is_anti_phishing' => (int)$request['is_anti_phishing'],
                            'anti_phishing_code' => $request['anti_phishing_code'],
                            'user_id' => $user->id,
                            'is_active' => false,
                        ];
                        UserAntiPhishing::create($data);
                    }
                }
            } else {
                Mail::queue(new MailVerifyAntiPhishing($user, $code, 'create'));
                if (!$userHasCode) {
                    $data = [
                        'is_anti_phishing' => (int)$request['is_anti_phishing'],
                        'anti_phishing_code' => $request['anti_phishing_code'],
                        'user_id' => $user->id,
                        'is_active' => false,
                    ];
                    UserAntiPhishing::create($data);
                }
            }
            DB::commit();
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex);
        }
    }

    public function insertAntiPhishing($request)
    {

    }

    public function genCodeAntiPhising($user, $phishingCode)
    {
        $code = uniqid(Str::random(60), true);
        $payload = [
            $user->id,
            $phishingCode,
            $code,
            Utils::currentMilliseconds() + (1000 * 60 * 30),
            now()->timestamp,
        ];

        return base64url_encode(implode('_', $payload));
    }

    public function decodeAntiPhishing($code)
    {
        $payload = explode('_', base64url_decode($code) . '_');
        $userId = $payload[0];
        $phishingCode = $payload[1];
        $expriedAt = $payload[3];
        $user = UserAntiPhishing::where([
            'user_id' => $userId,
            'anti_phishing_code' => $phishingCode,
        ])->first();
        $invalid = !$user || $expriedAt < Utils::currentMilliseconds() || $user->is_active;
        if ($invalid) {
            throw new HttpException(422, 'exception.verify_anti_phishing_invalid');
        }

        DB::beginTransaction();
        try {
            UserAntiPhishing::where('user_id', $userId)->update([
                'is_active' => false,
            ]);
            $user->is_active = true;
            $user->save();

            $data = [
                'id' => $userId,
                'antiPhishingCode' => $user?->anti_phishing_code
            ];
            $topic = Consts::TOPIC_PRODUCER_SYNC_ANTI_PHISHING_CODE;

            Utils::kafkaProducer($topic, $data);

            DB::commit();
            return $user;
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex);
        }
    }

    public function getUserNotificationSettings($userId)
    {
        return UserNotificationSetting::where('user_id', $userId)->whereIn('channel',
            Consts::NOTIFICATION_CHANNELS)->get();
    }

    public function getEnableUserNotificationSettings($userId)
    {
        return UserNotificationSetting::where('user_id', $userId)->select('channel', 'is_enable')->whereIn('channel',
            Consts::NOTIFICATION_CHANNELS)->get();
    }

    public function getUserNotificationSetting($userId, $channel)
    {
        if (!in_array($channel, Consts::NOTIFICATION_CHANNELS)) {
            return null;
        }
        DB::beginTransaction();
        try {
            $query = UserNotificationSetting::firstOrCreate(['channel' => $channel, 'user_id' => $userId],
                ['auth_key' => '', 'is_enable' => 0]);
            DB::commit();
            return $query;
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex);
        }
    }

    public function setUserNotificationSetting($userId, $channel, $key = null, $isEnable = null)
    {
        $userNotificationSetting = $this->getUserNotificationSetting($userId, $channel);

        if (!is_null($key)) {
            $userNotificationSetting->auth_key = $key;
        }

        if (!is_null($isEnable)) {
            $userNotificationSetting->is_enable = $isEnable;
        }
        $userNotificationSetting->save();
        return $userNotificationSetting;
    }

    public function enableUserNotificationSetting($userId, $channel)
    {
        return $this->setUserNotificationSetting($userId, $channel, null, true);
    }

    public function disableUserNotificationSetting($userId, $channel)
    {
        $this->setUserNotificationSetting($userId, $channel, null, false);
        $userNotificationSetting = $this->getUserNotificationSetting($userId, $channel);
        event(new UserNotificationUpdated($userId, $userNotificationSetting));

        return $userNotificationSetting;
    }

    public function getUserDevices($userId, $params = [])
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        return UserDeviceRegister::orderBy('created_at', 'desc')
            ->where('user_id', $userId)
            ->where('state', Consts::DEVICE_STATUS_CONNECTABLE)
            ->paginate($limit);
    }

    public function expiryDateVerification($userId): void
    {
        $securitySetting = UserSecuritySetting::where('id', $userId)->first();

        if ($securitySetting) {
            $securitySetting->email_verification_code = null;
            $securitySetting->email_verified = 1;
            $securitySetting->save();
        }
    }

    /**
     * @param $request
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|null
     */
    public function getSecuritySettings($request)
    {
        $user = $request->user();
        $table = $request->input('immediately') ? DB::connection('master')->table('user_security_settings') : DB::table('user_security_settings');
        return $table
            ->select('email_verified', 'phone_verified', 'identity_verified', 'bank_account_verified', 'otp_verified')
            ->where('id', $user->id)
            ->first();
    }

    public function updateBalance($userVoucher)
    {
        $userId = Auth::id();
        $table = $this->getTableWithType('spot', 'usdt');
        try {
            DB::transaction(function () use ($table, $userId, $userVoucher) {
                DB::table($table)->where('id', $userId)
                    ->update([
                        'balance' => DB::raw($table . '.balance + ' . $userVoucher->amount_old),
                        'available_balance' => DB::raw($table . '.available_balance + ' . $userVoucher->amount_old),
                    ]);

                DB::table('user_vouchers')->where('voucher_id', $userVoucher->voucher_id)
                    ->where('user_id', $userId)
                    ->update([
                        'status' => StatusVoucher::REDEEMED->value
                    ]);
            });

			//$isSpotMainBalance = env('DEPOSIT_WITHDRAW_SPOT_BALANCE', false);
			//if ($isSpotMainBalance) {
				//send balance to ME
				$amountClaim = $userVoucher->amount_old;
				try {
					$matchingJavaAllow = env("MATCHING_JAVA_ALLOW", false);
					if ($matchingJavaAllow) {
						//send kafka ME Deposit
						$typeName = 'deposit';

						if ($amountClaim < 0) {
							$amountClaim = BigNumber::new($amountClaim)->mul(-1)->toString();
						}

						$payload = [
							'type' => $typeName,
							'data' => [
								'userId' => $userId,
								'coin' => 'usdt',
								'amount' => $amountClaim,
								'voucherId' => $userVoucher->voucher_id
							]
						];

						$command = SpotCommands::create([
							'command_key' => md5(json_encode($payload)),
							'type_name' => $typeName,
							'user_id' => $userId,
							'obj_id' => $userVoucher->voucher_id,
							'payload' => json_encode($payload),

						]);
						if (!$command) {
							throw new HttpException(422, 'can not create command');
						}

						$payload['data']['commandId'] = $command->id;
						Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_COMMAND, $payload);

					}
				} catch (Exception $exx) {
					Log::error($exx);
					Log::error("++++++++++++++++++++ sendMEUserClaimVoucher: $userId, amount: $amountClaim");
				}

				if (env('SEND_BALANCE_LOG_TO_WALLET', false)) {
					SendBalanceLogToWallet::dispatch([
						'userId' => $userId,
						'walletType' => 'SPOT',
						'type' => 'DEPOSIT',
						'currency' => 'usdt',
						'currencyAmount' => $amountClaim,
						'currencyFeeAmount' => "0",
						'currencyAmountWithoutFee' => $amountClaim,
						'date' => Utils::currentMilliseconds()
					])->onQueue(Consts::QUEUE_BALANCE_WALLET);
				}
			//}
            return true;
        } catch (Exception $ex) {
            return false;
        }
    }

    public function updateBalanceFuture($asset, $userVoucher, $request)
    {
        $userId = Auth::id();
        $token = request()->header('authorization');
        $futureBaseUrl = env('FUTURE_API_URL');
        $futureAccessTokenUrl = $futureBaseUrl . '/api/v1/access-token';
        $futureDepositUrl = $futureBaseUrl . '/api/v1/account/deposit';
        try {
            DB::transaction(function () use (
                $request,
                $token,
                $futureDepositUrl,
                $userVoucher,
                $asset,
                $userId,
                $futureAccessTokenUrl
            ) {
                $resAccess = Http::withHeaders(['Authorization' => $token])->post($futureAccessTokenUrl, [
                    'token' => trim(str_replace('Bearer', '', $token))
                ]);
                if ($resAccess->failed()) {
                    throw new \ErrorException($resAccess->json()['info']['message']);
                }

                $resDeposit = Http::withHeaders(['Authorization' => $token])->put($futureDepositUrl, [
                    'amount' => $userVoucher->amount_old,
                    'asset' => $asset
                ]);
                if ($resDeposit->failed()) {
                    throw new \ErrorException($resDeposit->json()['info']['message']);
                }

                DB::table('user_vouchers')->where('voucher_id', $userVoucher->voucher_id)
                    ->where('user_id', $userId)
                    ->update([
                        'status' => StatusVoucher::REDEEMED->value
                    ]);

                return true;
            });
        } catch (Exception $e) {
            return [
                'status' => false,
                'msg' => $e->getMessage()
            ];
        }
    }

    public function checkQRcodeLogin($random, $qrcode)
    {
        $loginKey = $random;
        $redis = Redis::connection(Consts::RC_ORDER_PROCESSOR);
        $value = $redis->get($loginKey);
        $decodeValue = json_decode($value);

        if ($decodeValue && $decodeValue->random == $random && $decodeValue->qrcode == $qrcode) {
            return true;
        }

        return false;
    }

    public function mobileScanQRcode($random, $qrcode, $accessToken, $status)
    {
        $redis = Redis::connection(Consts::RC_ORDER_PROCESSOR);
        if ($status == 0 || $status == 1) { // mobile cancel: 0 of scanning: 1
            $data = (object)['random' => $random, 'qrcode' => $qrcode, 'status' => $status];
        } else { // mobile confirm: 2
            $data = (object)[
                'random' => $random,
                'qrcode' => $qrcode,
                'access_token' => $accessToken,
                'status' => $status
            ];
        }
        $ttl = $redis->ttl($random);
        if ($ttl > 0) {
            $redis->set($random, json_encode($data), 'ex', $ttl);
            return true;
        }
        return false;
    }

    public function getSumsubUserKycs($params)
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        return SumsubKYC::from(SumsubKYC::tableName(), 'user_kyc')
            ->select('user_kyc.id', 'users.email', 'user_kyc.full_name', 'user_kyc.country', 'user_kyc.id_number',
                'user_kyc.id_front', 'user_kyc.id_back', 'user_kyc.id_selfie', 'user_kyc.created_at', 'user_kyc.status', 'user_kyc.bank_status')
            ->join('users', 'user_kyc.user_id', '=', 'users.id')
            ->where('bank_status', '!=', 'init')
            ->when(!empty($params['search_key']), function ($query) use ($params) {
                $searchKey = $params['search_key'];
                $query->where(function ($q) use ($searchKey) {
                    $q->where('full_name', 'like', '%' . $searchKey . '%')
                        ->orWhere('country', 'like', '%' . $searchKey . '%')
                        ->orWhere('id_number', 'like', '%' . $searchKey . '%')
                        ->orWhere('email', 'like', '%' . $searchKey . '%');
                });
            })
            //->orderBy('status')
            ->when(
                !empty($params['sort']) && !empty($params['sort_type']),
                function ($query) use ($params) {
                    $query->orderBy($params['sort'], $params['sort_type']);
                },
                function ($query) use ($params) {
                    $query->orderBy('created_at', 'desc');
                }
            )
            ->paginate($limit);
    }

    public function getSumsubDetailUserKyc($params)
    {
        $userKyc = SumsubKYC::from(SumsubKYC::tableName(), 'user_kyc')
            ->select('user_kyc.id', 'users.email', 'user_kyc.full_name', 'user_kyc.country',
                'user_kyc.id_number', 'user_kyc.status', 'user_kyc.gender', 'user_kyc.id_front', 'user_kyc.id_back',
                'user_kyc.id_selfie', 'user_kyc.created_at', 'user_kyc.bank_status')
            ->join('users', 'user_kyc.user_id', '=', 'users.id')
            ->where('user_kyc.id', $params['kyc_id'])
            ->first();
        if ($userKyc) {
            $userKyc->id_front = Utils::getPresignedUrl($userKyc->id_front);
            $userKyc->id_back = Utils::getPresignedUrl($userKyc->id_back);
            $userKyc->id_selfie = Utils::getPresignedUrl($userKyc->id_selfie);
        }
        return $userKyc;
    }
}
