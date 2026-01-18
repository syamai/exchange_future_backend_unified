<?php

namespace App\Http\Controllers\Auth;

use App\Consts;
use App\Http\Services\Auth\ConfirmEmailService;
use App\Http\Services\Auth\DeviceService;
use App\Models\AccountProfileSetting;
use App\Models\UserDeviceRegister;
use App\Models\UserRegisterSource;
use App\Notifications\RegisterCompletedNotification;
use App\Models\User;
use App\Http\Controllers\AppBaseController;
use Carbon\Carbon;
use Faker\Factory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use App\Models\UserSecuritySetting;
use App\Mail\VerificationMailQueue;
use Illuminate\Support\Facades\Log;
use App\Jobs\CreateUserAccounts;
use App\Jobs\UpdateReferrerDetail;
use App\Http\Services\UserService;
use App\Utils;
use App\Rules\GoogleRecaptchaRule;
use App\Http\Services\GeetestService;
use App\Jobs\UpdateAffiliateTrees;
use League\OAuth2\Server\Exception\OAuthServerException;
use Illuminate\Support\Facades\Http;
use App\Models\CommissionBalance;
use App\Models\ReferrerRecentActivitiesLog;


class RegisterController extends AppBaseController
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected string $redirectTo = '/';

    private UserService $userService;
    private DeviceService $deviceService;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
        $this->userService = new UserService();
        $this->deviceService = new DeviceService();
    }

    public function showRegistrationForm(Request $request)
    {
        $userLocale = Utils::setLocale($request);
        $referrers = $this->userService->getAllReferrers();
        return view('auth.register')->with('userLocale', $userLocale)->with('referrers', $referrers);
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param array $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        $rules = [
            'email' => 'bail|required|string|email|max:255|unique_email|check_exist_email|mail_confirm_exists|check_email_blacklist',
            'password' => 'required|string|min:8|max:72|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/|confirmed|password_white_space',
            'agree_term' => 'required',
            'referrer_code' => 'unique_referrer_code'
        ];

        if (env('PHONE_VERIFY_REGISTER', false)) {
            $rules['mobile_code'] = 'required|string|exists:countries,country_code';
            $rules['phone'] = 'required|string|unique_phone|exists_phone_otp';
            $rules['otp_code'] = 'required|string|verify_phone_otp';
        }

        $messages = [
            'email.required' => 'email.required',
            'email.string' => 'email.string',
            'email.unique_email' => 'email.unique_email',
            'email.check_exist_email' => 'email.check_exist_email',
            'email.mail_confirm_exists' => 'email.mail_confirm_exists',
			'email.check_email_blacklist' => 'email.email_blacklist',
            'password.required' => 'password.required',
            'password.string' => 'password.string',
            'password.min' => 'password.min',
            'password.max' => 'password.max',
            'password.regex' => 'password.regex',
            'password.confirmed' => 'password.confirmed',
            'password.password_white_space' => 'password.password_white_space',
            'agree_term.required' => 'agree_term.required',
            'referrer_code.unique_referrer_code' => 'referrer_code.unique_referrer_code',
        ];

        return Validator::make($data, $rules, $messages);
    }

    /**
     * Handle a registration request for the application.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $userLocale = Utils::setLocale($request);

        $this->validator($request->all())->validate();

        $confirmationCode = Str::random(30);

        DB::beginTransaction();
        try {
            event(new Registered(
                $this->create(
                    $request->all(),
                    $confirmationCode
                )
            ));

            // Create commission balance record
            $user = User::query()->where('email', $request->email)->first();
            CommissionBalance::create([
                'id' => $user->id,
                'balance' => 0,
                'available_balance' => 0,
                'withdrawn_balance' => 0
            ]);
            Mail::queue(new VerificationMailQueue($request->email, $confirmationCode, $userLocale));

            DB::commit();
            return view('auth.register', [
                'success' => true,
                'registered_email' => $request->email
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error in register: " . $e->getMessage());
            throw $e;
        }
    }

    public function registerViaApi(Request $request)
    {
        if (request()->get('is_ggcaptcha', null)) {
            $validator = Validator::make(['ggToken' => request()->get('geetestData')], [
                'ggToken' => [new GoogleRecaptchaRule()]
            ]);
            if ($validator->fails()) {
                throw new OAuthServerException('google.recaptcha.errors', 6, 'recaptcha_failed');
            }
        } else {
            $result = GeetestService::secondaryVerify(request()->get('geetestData', []));
            if (!$result) {
                throw new OAuthServerException('exception.geetest.invalid', 6, 'geetest_failed');
            }
        }

        $userLocale = "en";
        if (!empty($request['lang'])) {
            $userLocale = $request['lang'];
        }
        app()->setLocale($userLocale);
        $this->validator($request->all())->validate();
        $confirmationCode = Str::random(30);

        $input = $request->all();
        $input['fake_name'] = Factory::create()->name;
        
        DB::beginTransaction();
        try {
            event(new Registered(
                $this->create($input, $confirmationCode)
            ));
            
            // Create commission balance record
            $user = User::query()->where('email', $request->email)->first();
            CommissionBalance::create([
                'id' => $user->id,
                'balance' => 0,
                'available_balance' => 0,
                'withdrawn_balance' => 0
            ]);

            self::setAccountProfileSettingSpot($request->email);
            Mail::queue(new VerificationMailQueue($request->email, $confirmationCode, $userLocale));

            DB::commit();
            return $this->sendResponse([]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error in registerViaApi: " . $e->getMessage());
            throw $e;
        }
    }

    private function setAccountProfileSettingSpot($email) {

        $user = User::query()->where('email', $email)->first();
        if(!$user || $user->AccountProfileSetting) return;
        return AccountProfileSetting::with('user')->create([
            'user_id' => $user->id,
            'spot_coin_pair_trade' => AccountProfileSetting::setCoinPairTradeDefault()
        ]);
    }

    private function getReferralCode($data)
    {
        if ($data['referrer_code']) {
            return $data['referrer_code'];
        }
        if (Session::has('referrer_code')) {
            return Session::get('referrer_code');
        }
        return null;
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param array $data
     * @return \App\Models\User
     */
    protected function create(array $data, $confirmationCode)
    {
        DB::beginTransaction();
        try {
            $user = User::firstOrNew([
                'email' => $data['email']
            ]);
            $phoneVerify = false;
            if (env('PHONE_VERIFY_REGISTER', false)) {
				$data['phone'] = str_replace(' ', '',$data['phone']);
				$user->mobile_code = $data['mobile_code'];
                $user->phone_number = $data['phone'];
                $user->phone_no = Utils::getPhone($data['phone'], $data['mobile_code']);
                $phoneVerify = true;
            }

            //$user->password = Utils::encrypt($data['password']);
            $user->password = bcrypt($data['password']);

            $referralCode = $this->getReferralCode($data);
            if ($referralCode) {
                $user->referrer_id = User::where('referrer_code', $referralCode)->value('id');
            }
            $user->referrer_code = $this->generateUniqueReferrerCode(6);
            $user->uid = generate_unique_uid();
            $user->fake_name = $data['fake_name'];
            $user->save();

            $setting = UserSecuritySetting::firstOrNew([
                'id' => $user->id
            ]);
            $setting->mail_register_created_at = Utils::currentMilliseconds();
            $setting->email_verification_code = $confirmationCode;
            if ($phoneVerify) {
                $setting->phone_verified = 1;
            }
            $setting->save();
            $this->deviceService->getCurrentDevice("", $user->id);

            // save info tracking utm
            UserRegisterSource::updateOrCreate(
                [
                    'user_id' => $user->id
                ],
                $data
            );


            // send new user to ME
            try {
                $matchingJavaAllow = env("MATCHING_JAVA_ALLOW", false);
                if ($matchingJavaAllow) {
                    //send kafka ME Deposit
                    $dataUser = [
                        'type' => "account",
                        'data' => [
                            [
                                'userId' => $user->id,
                                'spotTradingFeeAllow' => true,
                                'assets' => []
                            ]
                        ]
                    ];
                    Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_INIT, $dataUser);
                }
            } catch (\Exception $ex) {
                Log::error($ex);
            }
            DB::commit();

            session(['unconfirmed_email' => $data['email']]); //only put to session afater inserted to database

            return $user;
        } catch (\Exception $e) {
            Log::error("Error creating new user: " . $e->getMessage());
            DB::rollBack();
            throw $e;
        }
    }

    public function confirmEmail(Request $request)
    {
        if ($request->has('code')) {
            $setting = UserSecuritySetting::where([
                'email_verification_code' => $request->get('code'),
                'email_verified' => 0
            ])->first();

            if ($setting) {
                $setting->email_verified = 1;
                $setting->email_verification_code = null;
                $setting->save();

                $user = User::where('id', $setting->id)->first();
                $user->update(['status' => Consts::USER_ACTIVE]);


                CreateUserAccounts::dispatch($setting->id)->onQueue(Consts::QUEUE_BLOCKCHAIN);
                UpdateReferrerDetail::dispatch($setting->id);
                UpdateAffiliateTrees::dispatch($setting->id)->onQueue(Consts::QUEUE_UPDATE_AFFILIATE_TREE);

                // Send email register completed
                $user->notify(new RegisterCompletedNotification($user));

                return view('auth.confirm_email')->with(['result' => true]);
            }
        }

        return view('auth.confirm_email')->with(['result' => false]);
    }

    public function confirmEmailViaApi(Request $request)
    {
        try {
            DB::beginTransaction();
            $userId = null;
            if ($request->has('code')) {
                $setting = UserSecuritySetting::where([
                    'email_verification_code' => $request->get('code'),
                    'email_verified' => 0,
                ])->where('mail_register_created_at', '>=', Utils::previous24hInMillis())->first();
                if (!$setting) {
                    throw new \Exception('mail_registered_expired');
                }
                $userId = $setting->id;
                $confirmService = new ConfirmEmailService();
                $confirmService->confirm($request->code, $request->ip(),
                    $request->input('lang', Consts::DEFAULT_USER_LOCALE));
                UpdateReferrerDetail::dispatch($setting->id);
                UpdateAffiliateTrees::dispatch($setting->id)->onQueue(Consts::QUEUE_UPDATE_AFFILIATE_TREE);

                // log referrer registration
                $user_registration = User::find($userId);
                $referrer_id = $user_registration->referrer_id ?? null;
                $uid = $user_registration->uid;

                if ($referrer_id) {
                    $referrer = User::find($referrer_id);
                    ReferrerRecentActivitiesLog::create([
                        'user_id'    => $referrer_id,
                        'type'       => 'referral',
                        'target'     => $referrer->referrer_code ?? null,
                        'activities' => config('constants.referrer_message.register'),
                        'details'    => $uid,
                        'actor'      => 'role:admin',
                        'log_at'     => Utils::currentMilliseconds(),
                    ]);
                }
            }

            DB::commit();
            $userAdminConfirm = false;
            if ($userId) {
                $user = User::find($userId);
                if ($user->status == Consts::USER_WARNING) {
					$userAdminConfirm = true;
				}
//                $url = env('FUTURE_API_URL', 'http://localhost:3000') . env('FUTURE_USER_SYNC', '');
//                $res = Http::withHeaders([
//                    'Authorization' => 'Bearer ' . env('FUTURE_SECRET_KEY', '')
//                ])->post($url, $data);
//                Log::info("SYNC USER: " . $res);
                $data = [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role' => 'USER',
                    'status' => strtoupper($user->status),
                    'uid' => $user->uid,
					'isBot' => $user->type == Consts::USER_TYPE_BOT
                ];
                $topic = Consts::TOPIC_PRODUCER_SYNC_USER;

                Utils::kafkaProducer($topic, $data);
            }
            if ($userAdminConfirm) {
				return $this->sendError('user.admin_approve');
			}

            return $this->sendResponse([]);
        } catch (\Exception $exception) {
            DB::rollBack();
            logger($exception);

            return $this->sendError($exception->getMessage());
        }
    }

    public function resendConfirmEmail(Request $request)
    {
        $unconfirmedEmail = session('unconfirmed_email');

        $user = User::where('email', $unconfirmedEmail)->firstOrFail();

        $setting = UserSecuritySetting::findOrFail($user->id);

        if ($setting->email_verified == 1) {
            return view('auth.confirm_email')->with(['result' => true]);
        } else {
            $userLocale = Utils::setLocale($request);
            $locale = $request->get('lang', Consts::DEFAULT_USER_LOCALE);
            $confirmationCode = Str::random(30);
            $setting->email_verification_code = $confirmationCode;
            $setting->save();

            Mail::queue(new VerificationMailQueue($unconfirmedEmail, $confirmationCode, $locale));
            return view('auth.register', [
                'success' => true,
                'resend_email' => true,
                'registered_email' => $unconfirmedEmail
            ]);
        }
    }

    public function resendConfirmEmailViaApi(Request $request)
    {
        $emails = $request->params;
        if ($emails['email']) {
            $user = User::where('email', $emails['email'])->first();
            if (!$user) {
                abort(404, 'User not found');
            }
            $setting = UserSecuritySetting::findOrFail($user->id);
            if ($setting->email_verified == 1) {
                return $this->sendResponse([]);
            } else {
                $userLocale = Utils::setLocale($request);
                $locale = $request->get('lang', Consts::DEFAULT_USER_LOCALE);
                $confirmationCode = Str::random(30);
                $setting->mail_register_created_at = Utils::currentMilliseconds();
                $setting->email_verification_code = $confirmationCode;
                $setting->save();
                Mail::queue(new VerificationMailQueue($emails['email'], $confirmationCode, $locale));
                return $this->sendResponse([]);
            }
            return $this->sendResponse([]);
        }
    }

    private function generateUniqueReferrerCode($length = 6)
    {
        $str = Str::random($length);
        return (User::where('referrer_code', $str)->exists()) ? $this->generateUniqueReferrerCode($length) : $str;
    }
}
