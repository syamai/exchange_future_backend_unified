<?php

namespace App\Http\Services;


use App\Consts;
use App\IdentifierHelper;
use App\Jobs\SendDataToFutureEvent;
use App\Jobs\SendDataToServiceEvent;
use App\Jobs\SendDataToServiceGame;
use App\Models\AccountProfileSetting;
use App\Models\AffiliateTrees;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\User;
use App\Models\UserBlockchainAddresses;
use App\Models\UserSamsubKyc;
use App\Models\UserSecuritySetting;
use App\Notifications\KycFailNotify;
use App\Notifications\KycSuccessNotify;
use App\Utils\BigNumber;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Transaction\Models\Transaction;

class AccountService
{
    private $exportExelService;
    private $identifierHelper;
    private $spotService;
    private $userService;
    private $priceService;
    public function __construct(
        ExportExelService $exportExelService,
        IdentifierHelper $identifierHelper,
        SpotService $spotService,
        UserService $userService,
        PriceService $priceService
    )
    {
        $this->exportExelService = $exportExelService;
        $this->identifierHelper = $identifierHelper;
        $this->spotService = $spotService;
        $this->userService = $userService;
        $this->priceService = $priceService;
    }
    public function accountsHistory($request) {
        $total = User::query()->count();
        $verified = UserSecuritySetting::with('UserSecuritySetting')->where('email_verified','=', 1)->count();
        $verified2FA = User::query()->count('google_authentication');
        $deposit = UserBlockchainAddresses::whereHas('user')->groupby('user_id')->get()->count();

        $data['total']['account_total'] = $total;
        $data['total']['account_verified'] = $verified;
        $data['total']['account_secured'] = $verified2FA;
        $data['total']['account_deposited'] = $deposit;

        // Get the date 30 days ago from today
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        // Query the database to get the count of unique user IDs grouped by day
        $uniqueUserCounts = User::select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(DISTINCT id) as user_count'))
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'asc')
            ->get();

        $uniqueUserVerifiedCounts = UserSecuritySetting::select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(DISTINCT id) as user_verified'))
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'asc')
            ->get();

        $uniqueUserSecuredCounts = User::select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(DISTINCT id) as user_secured'))
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->where('google_authentication', '<>', '')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'asc')
            ->get();

        $uniqueUserDepositedCounts = UserBlockchainAddresses::select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(DISTINCT id) as user_deposited'))
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'asc')
            ->get();

        $data['sub30day']['account_total'] = $uniqueUserCounts;
        $data['sub30day']['account_verified'] = $uniqueUserVerifiedCounts;
        $data['sub30day']['account_secured'] = $uniqueUserSecuredCounts;
        $data['sub30day']['account_deposited'] = $uniqueUserDepositedCounts;
        return $data;
    }

    public function transactionsHistory($request)
    {
        $case = $request['currency'] ?? $request['params'];
        if (!$case) {
            $case = 'all';
        }

        return match ($case) {
            'currency' => $this->transactionHistoryParams($case),
            'all' => $this->transactionHistoryAllCoin(),
            default => $this->transactionHistoryData($case)
        };

    }
    public function transactionHistoryParams($params) {
        if(!$params) return [];
        return Transaction::whereHas('user')->groupby('currency')->pluck('currency');
    }

    public function transactionHistoryAllCoin()
    {
        $data = [
            'total' => [],
            'sub30day' => [
                'deposit' => [],
                'withdraw' => [],
                'deposit_usd' => [],
                'withdraw_usd' => []
            ]
        ];
        $prices = $this->priceService->getPrices(Consts::CURRENCY_USDT);

        $deposits = Transaction::where("status", Consts::TRANSACTION_STATUS_SUCCESS)
            ->filterDeposit()
            ->groupBy(['currency'])
            ->selectRaw("currency, sum(amount) as amount")
            ->get();

        $withdraws = Transaction::where("status", Consts::TRANSACTION_STATUS_SUCCESS)
            ->filterWithdraw()
            ->groupBy(['currency'])
            ->selectRaw("currency, sum(abs(amount)) as amount")
            ->get();

        $totalDeposit = BigNumber::new(0);
        $totalWithdraw = BigNumber::new(0);

        foreach ($deposits as $deposit) {
            $key = strtolower(Consts::CURRENCY_USDT . '_' . $deposit->currency);
            if (strtolower(Consts::CURRENCY_USDT) == strtolower($deposit->currency)) {
                $totalDeposit = $totalDeposit->add($deposit->amount);
            } elseif (isset($prices[$key])) {
                $totalDeposit = $totalDeposit->add(BigNumber::new($deposit->amount)->mul($prices[$key]->price)->toString());
            }
        }

        foreach ($withdraws as $withdraw) {
            $key = strtolower(Consts::CURRENCY_USDT . '_' . $withdraw->currency);
            if (isset($prices[$key])) {
                $totalWithdraw = $totalWithdraw->add(BigNumber::new($withdraw->amount)->mul($prices[$key]->price)->toString());
            } elseif (strtolower(Consts::CURRENCY_USDT) == strtolower($withdraw->currency)) {
                $totalWithdraw = $totalWithdraw->add($withdraw->amount);
            }
        }

        $data['total']['deposit'] = $totalDeposit->toString();
        $data['total']['withdraw'] = $totalWithdraw->toString();

        $data['total']['deposit_usd'] = Transaction::where("currency", Consts::CURRENCY_USD)
            ->where("status", Consts::TRANSACTION_STATUS_SUCCESS)
            ->filterDeposit()
            ->sum("amount");

        $data['total']['withdraw_usd'] = Transaction::where("currency", Consts::CURRENCY_USD)
            ->where("status", Consts::TRANSACTION_STATUS_SUCCESS)
            ->filterWithdraw()
            ->sum(DB::raw("abs(amount)"));

        // Query the database to get the count of unique user IDs grouped by day
        $thirtyDaysAgo = Carbon::now()->subDays(30)->getTimestamp()*1000;
        $deposits = Transaction::whereHas('user')
            ->select(DB::raw('DATE(FROM_UNIXTIME(updated_at / 1000)) as date'), 'currency', DB::raw('SUM(amount) as amount'))
            ->where('updated_at', '>=', $thirtyDaysAgo)
            ->where("status", Consts::TRANSACTION_STATUS_SUCCESS)
            ->filterDeposit()
            ->groupBy(['date', 'currency'])
            ->orderBy('date', 'asc')
            ->get();

        $dateDeposits = [];
        foreach ($deposits as $deposit) {
            $key = strtolower(Consts::CURRENCY_USDT . '_' . $deposit->currency);
            if (!isset($dateDeposits[$deposit->date])) {
                $dateDeposits[$deposit->date] = [
                    'date' => $deposit->date,
                    'amount' => 0
                ];
            }

            if (isset($prices[$key])) {
                $dateDeposits[$deposit->date]['amount'] = BigNumber::new($dateDeposits[$deposit->date]['amount'])->add(BigNumber::new($deposit->amount)->mul($prices[$key]->price))->toString();
            } elseif (strtolower(Consts::CURRENCY_USDT) == strtolower($deposit->currency)) {
                $dateDeposits[$deposit->date]['amount'] = BigNumber::new($dateDeposits[$deposit->date]['amount'])->add($deposit->amount)->toString();
            }

        }
        $data['sub30day']['deposit'] = array_values($dateDeposits);

        $withdraws = Transaction::whereHas('user')
            ->select(DB::raw('DATE(FROM_UNIXTIME(updated_at / 1000)) as date'), 'currency', DB::raw('SUM(abs(amount)) as amount'))
            ->where('updated_at', '>=', $thirtyDaysAgo)
            ->where("status", Consts::TRANSACTION_STATUS_SUCCESS)
            ->filterWithdraw()
            ->groupBy(['date', 'currency'])
            ->orderBy('date', 'asc')
            ->get();

        $dateWithdraws = [];
        foreach ($withdraws as $withdraw) {
            $key = strtolower(Consts::CURRENCY_USDT . '_' . $withdraw->currency);
            if (!isset($dateWithdraws[$withdraw->date])) {
                $dateWithdraws[$withdraw->date] = [
                    'date' => $withdraw->date,
                    'amount' => 0
                ];
            }
            if (strtolower(Consts::CURRENCY_USDT) == strtolower($withdraw->currency)) {
                $dateWithdraws[$withdraw->date]['amount'] = BigNumber::new($dateWithdraws[$withdraw->date]['amount'])->add($withdraw->amount)->toString();
            } else if (isset($prices[$key])) {
                $dateWithdraws[$withdraw->date]['amount'] = BigNumber::new($dateWithdraws[$withdraw->date]['amount'])->add(BigNumber::new($withdraw->amount)->mul($prices[$key]->price))->toString();
            }
        }
        $data['sub30day']['withdraw'] = array_values($dateWithdraws);

        $data['sub30day']['deposit_usd'] = Transaction::select(DB::raw('DATE(FROM_UNIXTIME(updated_at / 1000)) as date'), DB::raw('SUM(amount) as amount'))
            ->where('updated_at', '>=', $thirtyDaysAgo)
            ->where("currency", Consts::CURRENCY_USD)
            ->where("status", Consts::TRANSACTION_STATUS_SUCCESS)
            ->filterDeposit()
            ->groupBy(DB::raw('DATE(FROM_UNIXTIME(updated_at / 1000))'))
            ->orderBy('date', 'asc')
            ->get();

        $data['sub30day']['withdraw_usd'] = Transaction::select(DB::raw('DATE(FROM_UNIXTIME(updated_at / 1000)) as date'), DB::raw('SUM(abs(amount)) as amount'))
            ->where('updated_at', '>=', $thirtyDaysAgo)
            ->where("currency", Consts::CURRENCY_USD)
            ->where("status", Consts::TRANSACTION_STATUS_SUCCESS)
            ->filterWithdraw()
            ->groupBy(DB::raw('DATE(FROM_UNIXTIME(updated_at / 1000))'))
            ->orderBy('date', 'asc')
            ->get();

        return $data;
    }

    public function transactionHistoryData($currency) {
        if(!$currency) return [];
        $data['total']['deposit'] = Transaction::whereHas('user')
            ->where("currency", $currency)
            ->where("status", Consts::TRANSACTION_STATUS_SUCCESS)
            ->filterDeposit()
            ->sum("amount");

        $data['total']['withdraw'] = Transaction::whereHas('user')
            ->where("currency", $currency)
            ->where("status", Consts::TRANSACTION_STATUS_SUCCESS)
            ->filterWithdraw()
            ->sum(DB::raw("abs(amount)"));

        $data['total']['deposit_usd'] = Transaction::whereHas('user')
            ->where("currency", Consts::CURRENCY_USD)
            ->where("status", Consts::TRANSACTION_STATUS_SUCCESS)
            ->filterDeposit()
            ->sum("amount");

        $data['total']['withdraw_usd'] = Transaction::whereHas('user')
            ->where("currency", Consts::CURRENCY_USD)
            ->where("status", Consts::TRANSACTION_STATUS_SUCCESS)
            ->filterWithdraw()
            ->sum(DB::raw("abs(amount)"));

        // Get the date 30 days ago from today
        $thirtyDaysAgo = Carbon::now()->subDays(30)->getTimestamp()*1000;

        // Query the database to get the count of unique user IDs grouped by day
        $data['sub30day']['deposit'] = Transaction::whereHas('user')
            ->select(DB::raw('DATE(FROM_UNIXTIME(updated_at / 1000)) as date'), DB::raw('SUM(amount) as amount'))
            ->where('updated_at', '>=', $thirtyDaysAgo)
            ->where("currency", $currency)
            ->where("status", Consts::TRANSACTION_STATUS_SUCCESS)
            ->filterDeposit()
            ->groupBy(DB::raw('DATE(FROM_UNIXTIME(updated_at / 1000))'))
            ->orderBy('date', 'asc')
            ->get();

        $data['sub30day']['withdraw'] = Transaction::whereHas('user')
            ->select(DB::raw('DATE(FROM_UNIXTIME(updated_at / 1000)) as date'), DB::raw('SUM(abs(amount)) as amount'))
            ->where('updated_at', '>=', $thirtyDaysAgo)
            ->where("currency", $currency)
            ->where("status", Consts::TRANSACTION_STATUS_SUCCESS)
            ->filterWithdraw()
            ->groupBy(DB::raw('DATE(FROM_UNIXTIME(updated_at / 1000))'))
            ->orderBy('date', 'asc')
            ->get();

        $data['sub30day']['deposit_usd'] = Transaction::whereHas('user')
            ->select(DB::raw('DATE(FROM_UNIXTIME(updated_at / 1000)) as date'), DB::raw('SUM(amount) as amount'))
            ->where('updated_at', '>=', $thirtyDaysAgo)
            ->where("currency", Consts::CURRENCY_USD)
            ->where("status", Consts::TRANSACTION_STATUS_SUCCESS)
            ->filterDeposit()
            ->groupBy(DB::raw('DATE(FROM_UNIXTIME(updated_at / 1000))'))
            ->orderBy('date', 'asc')
            ->get();

        $data['sub30day']['withdraw_usd'] = Transaction::whereHas('user')
            ->select(DB::raw('DATE(FROM_UNIXTIME(updated_at / 1000)) as date'), DB::raw('SUM(abs(amount)) as amount'))
            ->where('updated_at', '>=', $thirtyDaysAgo)
            ->where("currency", Consts::CURRENCY_USD)
            ->where("status", Consts::TRANSACTION_STATUS_SUCCESS)
            ->filterWithdraw()
            ->groupBy(DB::raw('DATE(FROM_UNIXTIME(updated_at / 1000))'))
            ->orderBy('date', 'asc')
            ->get();

        return $data;
    }
    public function getListAccount($request)
    {
        $request['start_date'] = $request['start_date'] ? Carbon::createFromTimestampMs($request['start_date']) : "";
        $request['end_date'] = $request['end_date'] ? Carbon::createFromTimestampMs($request['end_date']) : "";

        $data =  User::whereHas('securitySetting')
            ->when($request['start_date'] && $request['end_date'], function ($query) use($request) {
                $query->where(function ($q) use($request) {
                    $q->whereBetween('created_at', [$request['start_date'], $request['end_date']])
                        ->orwhereBetween('updated_at', [$request['start_date'], $request['end_date']]);
                });
            })
            ->when($request['2fa'] != null, function ($query) use($request) {
                if($request['2fa']) $query->where('google_authentication', '<>', '');
                else $query->where('google_authentication', null);
            })
            ->when($request['status'], function ($query) use($request) {
                $query->where('status', $request['status']);
            })
            ->when($request['level'], function ($query) use($request) {
                $query->where('security_level', $request['level']);
            })
            ->when($request['s'], function ($query) use($request) {
                $query->where(function ($query) use ($request) {
                    $query->where('uid', 'like', "%{$request['s']}%")
                        ->orWhere('email', 'like', "%{$request['s']}%");
                });
            })
            ->when($request['kyc_status'], function ($query) use($request) {
                // Filter by kyc_status in the related userSamsubKYC model
                $query->whereHas('userSamsubKYC', function ($query) use ($request) {
                    $query->where('status', $request['kyc_status']);
                });
            })
            ->orderByDesc('created_at')
            ->when($request['page'] == -1, function ($query) {
                return $query->get();
            }, function ($query) use($request) {
                $size = $request['size'] ?? Consts::DEFAULT_PER_PAGE;
                return $query->paginate($size)
                    ->withQueryString();
            });

        if($request['page'] != -1) $data->getCollection();

        $data->transform(function ($item) {
            $user = User::with('userSamsubKYC')->find($item['id']);
            $kyc_status = optional($user->userSamsubKYC)->status ?? 0;

            $tmp = collect([
                'accountID' => $item['uid'],
                'user_id' => $item['id'],
                'level' => $item['security_level'],
                '2FA' => $item['google_authentication'] ? 1 : 0,
                'kyc_status' => $kyc_status,
                'creation_time' => $item['updated_at']
            ]);
            $take = collect($item)->except(['id','security_level', 'google_authentication']);

            return $tmp->merge($take);
        });

        return $data;
    }
    public function getProfile($id) {
        $user = User::with(['userSamsubKYC', 'securitySetting:id,phone_verified,identity_verified,otp_verified,email_verified'])->findOrFail($id);
        $tmp = collect([
            'kyc_status' => $user->userSamsubKYC->status ?? 0,
            'creation_time' => $user->created_at
        ]);
        $take = collect($user)->except(['user_samsub_k_y_c']);
        return $take->merge($tmp);
    }
    public function updateProfile($id, $params) {
        $user = User::whereHas('securitySetting')->findOrFail($id);
        if($params['status']) $user->status = $params['status'];
        $user->save();

        return $user;
    }
    public function profile($id, $method = 'GET', $params = []) {
        return match($method) {
            'GET' => $this->getProfile($id),
            'POST' => $this->updateProfile($id, $params),
            default => []
        };
    }
    public function settingProfile($id, $request, $method) {
        //dd($request);
        return match($method) {
            'GET' => $this->getSettingProfileSpot($id),
            'POST' => $this->updateSettingProfileSpot($id, $request),
            default => []
        };
    }

    public function getSettingProfileSpot($id) {
        $data = User::findOrFail($id)->AccountProfileSetting;
        if(!$data) {
            $data = AccountProfileSetting::with('user')->create([
                'user_id' => $id,
                'spot_coin_pair_trade' => AccountProfileSetting::setCoinPairTradeDefault()
            ]);
        }
        $data->spot_coin_pair_trade = collect(json_decode($data->spot_coin_pair_trade, true))
            ->filter(function ($v) {
                return $v == 1;
            })
            ->keys();

        return collect($data)->except([
            'future_trade_allow',
            'future_trading_fee_allow',
            'future_market_marker_allow',
            'future_coin_pair_trade',
        ]);
    }
    public function updateSettingProfileSpot($id, $params) {
        $data = User::findOrFail($id)->AccountProfileSetting; //dd($data);
        $spot_coin_pair_trade = $this->updateSettingProfileSpotCoinPair($params['spot_coin_pair_trade'], $params['coin_enable']);
        if(collect($spot_coin_pair_trade)->get('code') === 404) return $spot_coin_pair_trade;
        $data->update([
            'spot_trade_allow' => $params['spot_trade_allow'],
            'spot_trading_fee_allow' => $params['spot_trading_fee_allow'],
            'spot_market_marker_allow' => $params['spot_market_marker_allow'],
            'spot_coin_pair_trade' => $this->updateSettingProfileSpotCoinPair($params['spot_coin_pair_trade'], $params['coin_enable'])
        ]);
        return $data;
    }
    private function updateSettingProfileSpotCoinPair($pair, $coin_enable) {
        if(!$pair) return [];
        $diff = collect($pair)->diff($coin_enable);
        if($diff->all()) return ['code' => 404, 'COIN_PAIR_DISABLE_TRADING' => $diff->values()];

        $coin_pair = collect();
        foreach ($pair as $item) {
            list($coin, $currency) = explode("/", strtolower($item));
            $coin_pair->put("{$coin}/{$currency}", 1);
        }

        return collect($coin_pair)->toJson();
    }
    public function getListAccountKYC($request) { //dd($request['page']);
        $request['start_date'] = $request['start_date'] ? Carbon::createFromTimestampMs($request['start_date']) : "";
        $request['end_date'] = $request['end_date'] ? Carbon::createFromTimestampMs($request['end_date']) : "";

        $data = UserSamsubKyc::whereHas('user', function ($query) use ($request) {
                $query->when($request['s'], function ($q) use ($request) {
                    $q->where(function ($q) use($request) {
                        $q->where('uid', 'like', "%{$request['s']}%")
                        ->orWhere('email', 'like', "%{$request['s']}%");
                    });
                    
                });
            })
            ->where('bank_status', '!=', 'init')
            ->when($request['start_date'] && $request['end_date'], function ($query) use($request) {
                $query->where(function ($q) use($request) {
                    $q->whereBetween('created_at', [$request['start_date'], $request['end_date']])
                    ->orwhereBetween('updated_at', [$request['start_date'], $request['end_date']]);
                });
            })
            ->when($request['country'], function ($query) use($request) { $query->where('country', $request['country']);})
            ->when($request['status'], function ($query) use($request) { $query->where('status', $request['status']);})
            ->when(
                !empty($request['sort']) && !empty($request['sort_type']),
                function ($query) use ($request) {
                    $query->orderBy($request['sort'], $request['sort_type']);
                },
                function ($query) use ($request) {
                    $query->orderBy('updated_at', 'desc');
                }
            )
            ->when($request['page'] == -1, function ($query) {
                return $query->get();
            }, function ($query) use($request) {
                $size = $request['size'] ?? Consts::DEFAULT_PER_PAGE;
                return $query->paginate($size)
                    ->withQueryString();
            })

        ;
        if($request['page'] != -1) $data->getCollection();
        $data->transform(function ($item){
            $tmp = collect([
                'accountID' => $item->user->uid,
				'email' => $item->user->email,
                'creation_time' => $item['updated_at']
            ]);
            $take = collect($item)->except(['user']);
            return $tmp->merge($take);
        });
        return $data;
    }
    public function profileKYC($id, $method = 'GET', $params = []) {
        return match($method) {
            'GET' => $this->getProfileKYC($id),
            'POST' => $this->updateProfileKYC($id, $params),
            default => []
        };
    }
    public function getProfileKYC($id) {
        $user = UserSamsubKyc::whereHas('user')->findOrFail($id);
        $tmp = collect([
            'accountID' => $user->user->uid,
            'email' => $user->user->email ?? '',
            'creation_time' => $user['updated_at']
        ]);
        $take = collect($user)->except(['user']);

        return $tmp->merge($take);
    }
    public function updateProfileKYC($id, $params) {
        $user = UserSamsubKyc::on('master')->whereHas('user')->findOrFail($id);
        $oldStatus = $user->status;
        if($params['status']) $user->status = $params['status'];
        //update security level when user kyc
        if (isset($params['status']) && $oldStatus != $params['status']) {
        	$member = User::findOrFail($user->user_id);
        	if ($params['status'] == 'verified') {
				UserSecuritySetting::where('id', $user->user_id)->update(['identity_verified' => 1]);
				$this->userService->updateUserSecurityLevel($user->user_id);

				//send email kyc success
				$member->notify(new KycSuccessNotify());
				SendDataToServiceEvent::dispatch('kyc', $member->id);
                SendDataToFutureEvent::dispatch('kyc', $member->id);
                SendDataToServiceGame::dispatch('kyc', $member->id);

			} elseif ($params['status'] == 'rejected') {
        		//send email kyc fail
				$member->notify(new KycFailNotify());
			}

            $admin = Auth::guard('admin')->user();
        	if ($admin) {
        	    $user->admin_id = $admin->id;
            }

        }

        // Disable automatic timestamps for this save operation
        $user->timestamps = false;
        $user->save();
        // Re-enable timestamps after save
        $user->timestamps = true;

        return $user;
    }
    public function params($case, $key) {
        return match ($case) {
            'account' => $this->paramsAccount($key),
            'kyc' => $this->paramsAccountKyc($key),
            default => []
        };
    }
    public function paramsAccount($key) {
        /*return match ($key) {
            'level' => User::WhereHas('securitySetting')->groupby("security_level")->pluck("security_level"),
            '2fa' => [0,1],
            'status' => User::WhereHas('securitySetting')->groupby("status")->pluck("status"),
            'kyc_status' => UserSamsubKyc::whereHas('user')->groupby("status")->pluck("status"),
            default => []
        };*/
		return match ($key) {
			'level' => [Consts::SECURITY_LEVEL_EMAIL, Consts::SECURITY_LEVEL_IDENTITY, Consts::SECURITY_LEVEL_OTP, 4],
			'2fa' => [0,1],
			'status' => [Consts::USER_ACTIVE, Consts::USER_INACTIVE, Consts::USER_WARNING],
			'kyc_status' => [Consts::KYC_STATUS_PENDING, Consts::KYC_STATUS_VERIFIED, Consts::KYC_STATUS_REJECTED],
			default => []
		};
    }
    public function paramsAccountKyc($key) {
        return match ($key) {
            'country' => UserSamsubKyc::whereHas('user')->whereNotNull('country')->groupby('country')->pluck('country'),
            'status' => UserSamsubKyc::whereHas('user')->whereNotNull('status')->groupby("status")->pluck("status"),
            default => []
        };
    }
    public function export($request, $type) {
        $request['page'] = -1;
        return match ($type) {
            'account' => $this->exportAccountList($request),
            'kyc' => $this->exportAccountKycList($request),
            default => []
        };
    }
    public function exportAccountList($request) {
        $data = $this->getListAccount($request) ?? [];
        $uniqueIdentifier = $this->identifierHelper->generateUniqueIdentifier();
        $export = $this->exportExelService;
        $ext = $request->ext ?? 'csv';

        return $export->export($request, "exportAccountList_{$uniqueIdentifier}.{$ext}", $ext, 6, $data);
    }
    public function exportAccountKycList($request) {
        $data = $this->getListAccountKYC($request) ?? [];
        $uniqueIdentifier = $this->identifierHelper->generateUniqueIdentifier();
        $export = $this->exportExelService;
        $ext = $request->ext ?? 'csv';

        return $export->export($request, "exportAccountKycList_{$uniqueIdentifier}.{$ext}", $ext, 7, $data);
    }
    public function spotCase($case, $id, $request, $method = 'GET') {
        $request['user_id'] = $id;
        $request['type'] = null;
        return match($case) {
            'profile' => $this->settingProfile($id, $request, $method),
            'open' => $this->spotService->ordersOpen($request),
            'history' => $this->spotService->ordersHistory($request),
            'trade' => $this->spotService->ordersTradeHistory($request),
            default => []
        };
    }
    public function getHistoryCase($case, $id, $request) {
        return match($case) {
            'activity' => $this->getActivity($id, $request),
            default => []
        };
    }

    public function getActivity($id, $request) {
        $size = $request['size'] ?? Consts::DEFAULT_PER_PAGE;
        $data = User::findOrfail($id)
            ->userDeviceRegisters()
            ->paginate($size)->withQueryString();
        $data->transform(function($item) {
            return [
                'id' => $item['id'],
                'operating_system' => $item['operating_system'],
                'platform' => $item['platform'],
                'ip' => $item['latest_ip_address'],
                'creation_time' => $item['updated_at']
            ];
        });

        return $data;
    }
    public function getAffiliateTree($case, $id, $request) {
        return match($case) {
            'down' => $this->getAffiliateTreeDown($id, $request),
            'up' => $this->getAffiliateTreeUp($id, $request),
            default => []
        };
    }

    public function getAffiliateTreeDown($id, $request) {
        $data = AffiliateTrees::with('userDown')
            ->where('referrer_id', $id)
            ->when($request['s'], function ($query) use($request) {
                $query->where('user_id', 'like', "%{$request['s']}%");
            })
            ->paginate(Arr::get($request, 'size', Consts::DEFAULT_PER_PAGE))->withQueryString();

        $data->transform(function ($item) {
            return [
                'accountID' => $item->userDown()->first()->uid,
                'email' => $item->userDown()->first()->email,
                'level' => $item['level'],
                'creation_time' => $item['created_at']
            ];
        });

        return $data;
    }
    public function getAffiliateTreeUp($id, $request) {
        $data = AffiliateTrees::with('userUp')
            ->where('user_id', $id)
            ->when($request['s'], function ($query) use($request) {
                $query->where('referrer_id', 'like', "%{$request['s']}%");
            })
            ->paginate(Arr::get($request, 'size', Consts::DEFAULT_PER_PAGE))->withQueryString();

        $data->transform(function ($item) {
            return [
                'accountID' => $item->userUp()->first()->uid,
                'email' => $item->userUp()->first()->email,
                'level' => $item['level'],
                'creation_time' => $item['created_at']
            ];
        });
        return $data;
    }

    public function getTransactionsCase($case, $id, $request) {
        return match($case) {
            'withdraw' => $this->getTransactionsWithdraw($id, $request),
            'deposit' => $this->getTransactionsDeposit($id, $request),
            'transfer' => $this->getTransactionsTransfer($id, $request)
        };
    }
    public function getTransactionsWithdraw($id, $request) {
        $request['user_id'] = $id;
        $request['trans_type'] = Consts::TRANSACTION_TYPE_WITHDRAW;
        return $this->spotService->withdraw($request);
    }
    public function getTransactionsDeposit($id, $request) {
        $request['user_id'] = $id;
        $request['trans_type'] = Consts::TRANSACTION_TYPE_DEPOSIT;
        return $this->spotService->desposit($request);
    }
    public function getTransactionsTransfer($id, $request) {
        $request['start_date'] = $request['start_date'] ? Carbon::createFromTimestampMs($request['start_date']) : "";
        $request['end_date'] = $request['end_date'] ? Carbon::createFromTimestampMs($request['end_date']) : "";
        $data = User::findOrfail($id)->transferHistory()
            ->when($request['start_date'] && $request['start_date'], function ($query) use($request) {
                $query->whereBetween('updated_at', [$request['start_date'], $request['end_date']]);
            })
            ->orderByDesc('updated_at')
            ->paginate(Arr::get($request, 'size', Consts::DEFAULT_PER_PAGE))->withQueryString();

        $data->map(function ($item) {
            $item->status = Consts::TRANSACTION_STATUS_SUCCESS;
        });
        return $data;
    }

    public function getLogsCase($case, $id, $request) {

        return match($case) {
            'balance' => $this->getLogsSpot($id, $request),
            default => [],
        };
    }

    public function getLogsSpot($id, $request) {
        return OrderTransaction::OrderTransactions($id)
            ->when($request['status'], function ($query) use($request){
                $query->where('status', Arr::get($request, 'status', Consts::ORDER_STATUS_EXECUTED));
            })
            ->when($request['start_date'] && $request['end_date'], function ($query) use($request) {
                $query->whereBetween('created_at', [$request['start_date'] , $request['end_date']]);
            })
            ->when($request['currency'], function ($query) use($request) {
                $query->where('currency', $request['currency']);
            })
            ->when($request['coin'], function ($query) use($request) {
                $query->where('coin', $request['coin']);
            })
            ->when($request['type'], function ($query) use($request) {
                $query->where('transaction_type', $request['type']);
            })
            ->orderByDesc('created_at')->paginate(Arr::get($request, 'size', Consts::DEFAULT_PER_PAGE));
    }

    public function getBalanceCase($case, $id, $request) {
        return match($case) {
            'spot' => $this->getBalanceSpot($id, $request),
            default => []
        };
    }

    public function getBalanceSpot($id) {
        $spot = $this->userService->getUserAccounts($id);
        $data = collect($spot)->only('spot')->values();
        $result = $data->map(function ($item) use($id) {
            return collect($item)->transform(function ($item2, $k) use($id) {
                $item = collect($item2);
                if($item->hasAny(['balance', 'available_balance'])) {
                    $amounts = self::inOrderSpot($id);
                    $item->put('in_orders', collect($amounts)->get($k));
                } else $item = $item2;
                return $item;
            });
        });

        return $result;
    }
    public function inOrderSpot($id) {
        $orders = Order::query()
            ->where('user_id', $id)
            ->whereIn('status', [Consts::ORDER_STATUS_EXECUTING, Consts::ORDER_STATUS_PENDING])
            ->get();

        // Combine 'coin' and 'currency' fields, remove duplicates
        $coins = $orders->pluck('coin')
            ->merge($orders->pluck('currency'))
            ->unique()
            ->values();

        return $coins->map(function ($coin) use ($orders) {
            // Filter orders for the current coin where trade_type is SELL or BUY
            $orders_by_coin = $orders->filter(function ($order) use ($coin) {
                return $order->coin === $coin && $order->trade_type === Consts::ORDER_TRADE_TYPE_SELL;
            });

            $orders_by_currency = $orders->filter(function ($order) use ($coin) {
                return $order->currency === $coin && ($order->trade_type === Consts::ORDER_TRADE_TYPE_BUY && ($order->type == Consts::ORDER_TYPE_LIMIT || $order->type == Consts::ORDER_TYPE_STOP_LIMIT));
            });
            $amount = null;
            // Calculate amounts for 'coin'
            if ($orders_by_coin->isNotEmpty()) {
                $amount = $orders_by_coin->sum(function ($order) {
                    return BigNumber::new($order->quantity)->toString();  // Use BigNumber for precision
                });
            }

            // Calculate amounts for 'currency'
            if ($orders_by_currency->isNotEmpty()) {
                $amount = $orders_by_currency->sum(function ($order) {
                    return BigNumber::new($order->price)->mul($order->quantity)->toString();  // Multiply price by quantity with precision
                });

            }
            return [$coin => BigNumber::new($amount)->toString()];
        })->collapse();

    }
    public function amountOrderSpot($order) {
        $amount = null;
        if ($order->trade_type == Consts::ORDER_TRADE_TYPE_BUY) {
            if ($order->type == Consts::ORDER_TYPE_LIMIT || $order->type == Consts::ORDER_TYPE_STOP_LIMIT) {
                $amount = BigNumber::new($order->price)->mul($order->quantity)->toString();
            }
        } else {
            $amount = BigNumber::new($order->quantity)->toString();
        }
        return $amount;
    }
}