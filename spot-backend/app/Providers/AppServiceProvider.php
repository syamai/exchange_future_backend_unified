<?php

namespace App\Providers;

use App\Facades\AmalCalculator;
use App\Facades\CheckFa;
use App\Facades\CheckFun;
use App\Facades\FormatFun;
use App\Facades\UploadFun;
use App\Models\AmalSetting;
use App\Models\EmailFilter;
use App\Models\PhoneOtp;
use App\Models\SiteSetting;
use App\Utils;
use Bugger\BuggerServiceProvider;
use Bugger\EventBuggerServiceProvider;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use App\Http\Services\MasterdataService;
use App\Models\User;
use Illuminate\Support\Facades\App;
use App\Consts;
use App\Exports\Module\ExcelDataExport;
use Illuminate\Support\Str;
use IPActive\IPActiveServiceProvider;
use L5Swagger\L5SwaggerServiceProvider;
use Laravel\Passport\Passport;
use Snapshot\SnapshotServiceProvider;
use Transaction\TransactionServiceProvider;
use Carbon\Carbon;
use App\Models\IndexSetting;
use App\Utils\BigNumber;
use PassportHmac\PassportHmacServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        JsonResource::withoutWrapping();

        Schema::defaultStringLength(191);
        URL::forceScheme('https');

        Validator::extend('unique_email', function ($attribute, $value, $parameters, $validator) {
            $user = User::where('email', $value)->first();
            if ($user) {
                return !$user->securitySetting->email_verified;
            }
            return true;
        });

        // if user not verify and mail confirm exists
        Validator::extend('mail_confirm_exists', function ($attribute, $value, $parameters, $validator) {
            $user = User::where('email', $value)->with('securitySetting')
                ->whereHas('securitySetting', function ($q) {
                    $q->where('email_verified', 0);
                })->first();

            if (!$user || !$user->securitySetting->mail_register_created_at) {
                return true;
            }

            $now = Carbon::now()->timestamp;
            $expiredTime = Carbon::createFromTimestamp($user->securitySetting->mail_register_created_at / 1000)->addHours(24)->timestamp;

            if ($now < $expiredTime) {
                return false;
            }

            return true;
        });

        // if user not verify and mail expired
        Validator::extend('check_exist_email', function ($attribute, $value, $parameters, $validator) {
            $user = User::where('email', $value)->with('securitySetting')
                ->whereHas('securitySetting', function ($q) {
                    $q->where('email_verified', 0);
                })->first();

            if (!$user || !$user->securitySetting->mail_register_created_at) {
                return true;
            }

            $now = Carbon::now()->timestamp;
            $expiredTime = Carbon::createFromTimestamp($user->securitySetting->mail_register_created_at / 1000)->addHours(24)->timestamp;

            if ($now > $expiredTime) {
                return false;
            }

            return true;
        });

		Validator::extend('check_email_blacklist', function ($attribute, $value, $parameters, $validator) {
			$domain = trim(strtolower(explode('@', $value)[1] ?? ''));
			if ($domain) {
				return !EmailFilter::where('domain', $domain)
					->where('type', Consts::TYPE_BLACKLIST)
					->exists();
			}

			return false;
		});



        Validator::extend('unique_referrer_code', function ($attribute, $value, $parameters, $validator) {
            return User::where('referrer_code', $value)->exists();
        });

        Validator::extend('password_white_space', function ($attribute, $value, $parameters, $validator) {
            return is_int(strpos($value, ' ')) ? false : true;
        });

        Validator::extend('verified_email', function ($attribute, $value, $parameters, $validator) {
            $user = User::where('email', $value)->first();
            if ($user) {
                return $user->securitySetting->email_verified;
            }
            return true;
        });
        Validator::extend('correct_password', function ($attribute, $value, $parameters, $validator) {
            $user = Auth::user();
            //$passEncrypt = Utils::encrypt($value);
            //return $passEncrypt == $user->password;
            return (password_verify($value, $user->password));
        });

        Validator::extend('correct_otp', function ($attribute, $value, $parameters, $validator) {
            $user = Auth::user();
            return $user->verifyOtp($value);
        });

        Validator::extend('otp_not_used', function ($attribute, $value, $parameters, $validator) {
            $userId = Auth::id();
            $key = "OTPCode:{$userId}:{$value}";
            return Cache::get($key) !== $value;
        });

        Validator::extend('otp_or_google_auth_required', function ($attribute, $value, $parameters, $validator) {
            return $value != '|';
        });

        Validator::extend('verify_otp_or_google_auth', function ($attribute, $value, $parameters, $validator) {
            $otp = explode('|', $value)[0];
            $googleAuth = explode('|', $value)[1];
            return Auth::user()->verifyOtp($otp) || Auth::user()->google_authentication == $googleAuth;
        });

        Validator::extend('verify_otp_recovery_code', function ($attribute, $value, $parameters, $validator) {
            $user = User::where('email', $parameters[0])->first();
            //$passEncrypt = Utils::encrypt($parameters[1]);
            //return $user && ($passEncrypt == $user->password) && $user->google_authentication == $value;
            return $user && password_verify($parameters[1], $user->password) && $user->google_authentication == $value;
        });

        Validator::extend('verify_otp_recovery_code_with_auth', function ($attribute, $value, $parameters, $validator) {
            return Auth::user()->google_authentication == $value;
        });

        Validator::extend('amal_amount', function ($attribute, $value, $parameters, $validator) {
            $amount = AmalSetting::query()->first()->amount;
            return !((empty($amount) || $amount < $value));
        });

        Validator::extend('otp', function ($attribute, $value, $parameters, $validator) {
            try {
                $googleOtp = explode('|', $value)[0];
                $smsOtp = explode('|', $value)[1];
                if ($googleOtp) {
                    return Auth::user()->verifyOtp($googleOtp);
                } else {
                    return Auth::user()->verifySmsOtp($smsOtp);
                }
            } catch (\Exception $e) {
                Log::error($e);
                return false;
            }
        });

        View::composer('*', function ($view) {
            $dataVersion = MasterdataService::getDataVersion();
            $settings = MasterdataService::getOneTable('settings')->mapWithKeys(function ($item) {
                return [$item->key => $item->value];
            });
            $banner = SiteSetting::updateOrCreate([
                'banner' => Utils::getBannerMailTemplate(Str::random('46'))
            ]);
            $footer = SiteSetting::updateOrCreate([
                'footer' => Utils::getFooterMailTemplate(Str::random('46'))
            ]);

            $view->with('dataVersion', $dataVersion)
                ->with('userLocale', App::getLocale())
                ->with('setting', $settings)
                ->with('banner', $banner)
                ->with('footer', $footer);
        });

        Validator::extend('is_withdrawal_address', function ($attribute, $value, $parameters, $validator) {
            $coin = $parameters[0];
            $blockchain_sub_address = $parameters[1];
            switch ($coin) {
                case 'usd':
                    //TO DO
                    return preg_match('/^.+$/', $value);
                case "xrp":
                    return preg_match('/^r[rpshnaf39wBUDNEGHJKLM4PQRST7VWXYZ2bcdeCg65jkm8oFqi1tuvAxyz]{27,35}$/', $value) && intval($blockchain_sub_address) < pow(2, 32);
                case "etc":
                    return preg_match('/^[0-9A-HJ-NP-Za-km-z]{26,35}$/', $value);
                case "eth":
                    if (preg_match('/^(0x)?[0-9a-fA-F]{40}$/', $value)) {
                        return true;
                    }
                    return false;
                case "btc":
                case "bch":
                case "wbc":
                    return preg_match('/^[123mn][1-9A-HJ-NP-Za-km-z]{26,35}$/', $value);
                case "dash":
                    return preg_match('/^[0-9A-HJ-NP-Za-km-z]{26,35}$/', $value);
                case "ltc":
                    return preg_match('/^[1-9A-HJ-NP-Za-km-z]{26,35}$/', $value);
            }
        });

        Validator::extend('valid_currency_address', function ($attribute, $value, $parameters, $validator) {
            $currency = $parameters[0];
            $blockchain_address = $parameters[1];
            $networkId = $parameters[2];

            return CheckFa::blockchainAddress($currency, $blockchain_address, $networkId);
        });

        Validator::extend('user_id_coin_wallet', function ($attribute, $value, $parameters, $validator) {
            $coin = strtolower(Arr::get($validator->getdata(), $parameters[1]));
            if ($coin == Consts::CURRENCY_XRP || $coin == Consts::CURRENCY_EOS) {
                return true;
            } else {
                $userWithdrawlAddress = \App\Models\UserWithdrawalAddress::where([
                    'user_id'           => Auth::id(),
                    'wallet_address'    => $value,
                    'wallet_sub_address' => Arr::get($validator->getdata(), $parameters[0]),
                    'coin'              => Arr::get($validator->getdata(), $parameters[1]),
                ]);
                return !$userWithdrawlAddress->exists();
            }
        });
        Validator::extend('user_id_coin_wallet_xpr_eos', function ($attribute, $value, $parameters, $validator) {
            $coin = strtolower(Arr::get($validator->getdata(), $parameters[1]));
            if ($coin == Consts::CURRENCY_XRP || $coin == Consts::CURRENCY_EOS) {
                $userWithdrawlAddress = \App\Models\UserWithdrawalAddress::where([
                    'user_id'           => Auth::id(),
                    'wallet_address'    => $value,
                    'wallet_sub_address' => Arr::get($validator->getdata(), $parameters[0]),
                    'coin'              => Arr::get($validator->getdata(), $parameters[1]),
                ]);
                return !$userWithdrawlAddress->exists();
            } else {
                return true;
            }
        });

        Validator::extend('valid_contract_address', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^[0-9A-HJ-NP-Za-km-z]{35,45}$/', $value);
        });

        Validator::extend('instrument_check_require_perpetual', function ($attribute, $value, $parameters, $validator) {
            $params = $validator->getdata();
            $type = @$params["type"] ?? 0;
            if ($type != 2) {
                return true;
            }
            $val = trim($params[$attribute]);
            if (empty($val)) {
                return false;
            }
            return true;
        });
        Validator::extend('instrument_check_require_future', function ($attribute, $value, $parameters, $validator) {
            $params = $validator->getdata();
            $type = @$params["type"] ?? 0;
            if ($type != 1) {
                return true;
            }
            $val = trim($params[$attribute]);
            if (empty($val)) {
                return false;
            }
            return true;
        });
        Validator::extend('fee_condition', function ($attribute, $value, $parameters, $validator) {
            $params = $validator->getdata();
            if (array_key_exists("maker_fee", $params)) {
                if (BigNumber::new($params[$attribute])->add($params["maker_fee"])->comp(0) >= 0) {
                    return true;
                }
                return false;
            }
            return false;
        });
        Validator::extend('settlement_fee_condition', function ($attribute, $value, $parameters, $validator) {
            $params = $validator->getdata();
            if (BigNumber::new($params[$attribute])->comp(0) > 0) {
                return true;
            }
            return false;
        });
        Validator::extend('instrument_check_small_than_init_margin', function ($attribute, $value, $parameters, $validator) {
            $params = $validator->getdata();
            if ($params[$attribute] < $params["init_margin"]) {
                return true;
            }
            return false;
        });
        Validator::extend('instrument_check_small_than_risk_limit', function ($attribute, $value, $parameters, $validator) {
            $params = $validator->getdata();
            if ($params[$attribute] < $params["risk_limit"]) {
                return true;
            }
            return false;
        });
        Validator::extend('instrument_check_difficult_than', function ($attribute, $value, $parameters, $validator) {
            $params = $validator->getdata();
            if ($params[$attribute] == $params[$parameters[0]]) {
                return false;
            }
            return true;
        });
        Validator::extend('instrument_check_refe_index', function ($attribute, $value, $parameters, $validator) {
            $params = $validator->getdata();
            $reference = @$params[$attribute] ?? null;
            if ($reference == null) {
                return false;
            }
            $check = IndexSetting::where('root_symbol', $params['root_symbol'])->where('symbol', $reference)->where('status', 'active')->first();
            if (!$check) {
                return false;
            }
            return true;
        });
        Validator::extend('instrument_check_funding_base', function ($attribute, $value, $parameters, $validator) {
            $params = $validator->getdata();
            $type = @$params["type"] ?? 0;
            if ($type != 2) {
                return true;
            }
            $val = trim($params[$attribute]);
            if (empty($val)) {
                return false;
            }
            $check = IndexSetting::where('root_symbol', $params['base_underlying'])->where('symbol', $val)->where('status', 'active')->first();
            if (!$check) {
                return false;
            }
            return true;
        });
        Validator::extend('instrument_check_funding_quote', function ($attribute, $value, $parameters, $validator) {
            $params = $validator->getdata();
            $type = @$params["type"] ?? 0;
            if ($type != 2) {
                return true;
            }
            $val = trim($params[$attribute]);
            if (empty($val)) {
                return false;
            }
            $check = IndexSetting::where('root_symbol', $params['quote_currency'])->where('symbol', $val)->where('status', 'active')->first();
            if (!$check) {
                return false;
            }
            return true;
        });

        Validator::extend('check_enable_deposit_withdrawal', function ($attribute, $value, $parameters, $validator) {

            /*$res = MasterdataService::getOneTable('coins_confirmation')->filter(function ($item, $key) use ($value) {
                return $item->coin == $value;
            })->first();

            if (!$res) {
                return false;
            }

            $enable = ($parameters[0] == 'deposit') ? $res->is_deposit : $res->is_withdraw;

            return !!$enable;*/
            $isDeposit = !empty($parameters[0]) && $parameters[0] == 'deposit';
            $networkCoins = DB::table('coins', 'c')
                ->join('network_coins as nc', 'c.id', 'nc.coin_id')
                ->join('networks as n', 'nc.network_id', 'n.id')
                ->where([
                    'c.coin' => $value,
                    'n.enable' => true,
                    'nc.network_enable' => true,

                ])
                ->when($isDeposit, function ($query) {
                    $query->where([
                        'n.network_deposit_enable' => true,
                        'nc.network_deposit_enable' => true,
                    ]);
                }, function ($query) {
                    $query->where([
                        'n.network_withdraw_enable' => true,
                        'nc.network_withdraw_enable' => true,
                    ]);
                })
                ->select(['c.coin', 'nc.network_id'])
                ->get();
            return !$networkCoins->isEmpty();
        });

        Validator::extend('check_min_amount_transfer', function ($attribute, $value, $parameters, $validator) {
            $amount = new BigNumber($value);
            if($amount->comp(Consts::MIN_AMOUNT_TRANSFER) === -1){
                return false;
            }

            return true;
        }, 'amount larger than 0.00000001');

        Validator::extend('unique_phone', function ($attribute, $value, $parameters, $validator) {
            $params = $validator->getdata();
            $phoneNumber = str_replace(' ', '', $value);
            $mobileCode = isset($params['mobile_code']) ? $params['mobile_code'] : '';
            $phone = Utils::getPhone($phoneNumber, $mobileCode);
            if ($phone) {
                $user = User::where('phone_no', $phone)->first();
                return $user ? false : true;
            }
            return false;
        });

        Validator::extend('exists_phone_otp', function ($attribute, $value, $parameters, $validator) {
            $params = $validator->getdata();
            $phoneNumber = str_replace(' ', '', $value);
            $mobileCode = isset($params['mobile_code']) ? $params['mobile_code'] : '';
            $phone = Utils::getPhone($phoneNumber, $mobileCode);
            if ($phone) {
                $phoneOtp = PhoneOtp::where('phone', $phone)->first();
                if ($phoneOtp) {
                    return true;
                }
            }
            return false;
        });

        Validator::extend('verify_phone_otp', function ($attribute, $value, $parameters, $validator) {
            $params = $validator->getdata();
            $phoneNumber = str_replace(' ', '', isset($params['phone']) ? $params['phone'] : '');
            $mobileCode = isset($params['mobile_code']) ? $params['mobile_code'] : '';
            $phone = Utils::getPhone($phoneNumber, $mobileCode);
            if ($phone) {
                $otpRecord = PhoneOtp::where('phone', $phone)->first();

                if (!$otpRecord || $otpRecord->otp_code !== $value) {
                    return false;
                }

                if ($otpRecord->isExpired()) {
                    return false;
                }
                return true;
            }
            return false;
        });

        DB::enableQueryLog();
        DB::listen(function ($query) {
            // $ignoreKyes = ['insert into `jobs`', 'select * from `jobs`'];
            // foreach ($ignoreKyes as $key) {
            //     if (substr($query->sql, 0, strlen($key)) === $key) {
            //         return;
            //     }
            // }

            Log::debug('SQL', [
                'session' => session()->getId(),
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'runtime' => $query->time
            ]);
            DB::flushQueryLog();
        });

	    Passport::withoutCookieSerialization();

        // if (config('app.env') === 'local') {
        //     URL::forceScheme('http');
        // }

    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('AmalCalculator', AmalCalculator::class);

        $this->app->bind('CheckFa', CheckFun::class);
        $this->app->bind('FormatFa', FormatFun::class);
        $this->app->bind('UploadFa', UploadFun::class);

        $this->app->register(BuggerServiceProvider::class);
        $this->app->register(EventBuggerServiceProvider::class);
        //        // $this->app->register(LeaderBoardServiceProvider::class);
        $this->app->register(SnapshotServiceProvider::class);
        $this->app->register(IPActiveServiceProvider::class);
        //        // $this->app->register(MAMServiceProvider::class);
        $this->app->register(TransactionServiceProvider::class);
        $this->app->register(PassportHmacServiceProvider::class);
        $this->app->register(L5SwaggerServiceProvider::class);

        $this->registerFacadeAccessors();
    }

    /**
     * Register facade accessors for repository implementations.
     */
    protected function registerFacadeAccessors(): void
    {
        /**
         * Export Facades
         */
        $this->app->singleton('DataExport', function ($app) {
            return $app->make(ExcelDataExport::class);
        });

    }
}
