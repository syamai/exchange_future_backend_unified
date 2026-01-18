<?php

namespace App\Models;

use App\Consts;
use App\Utils;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use App\Notifications\ResetPassword as ResetPasswordNotification;
use PHPGangsta_GoogleAuthenticator;
use Carbon\Carbon;
use Transaction\Models\Transaction;

class User extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;
	use SoftDeletes;

    const STATUS_ACTIVE = 'active';
    const OTP_CACHE_LIVE_TIME = 30; // 30s

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'status', 'is_partner', 'partner_registered_at', 'registered_at'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'google_authentication'
    ];

    public function favorites()
    {
        return $this->hasMany('App\Models\UserFavorite');
    }

    public function devices()
    {
        return $this->hasMany('App\Models\UserDeviceRegister')->orderByDesc('updated_at');
    }

    public function userSession()
    {
        return $this->hasOne('App\Models\UserSession');
    }

    public function userFeeLevel()
    {
        $today = Carbon::now(Consts::DEFAULT_TIMEZONE)->startOfDay();
        $activeTime = $today->timestamp * 1000;
        return $this->hasOne('App\Models\UserFeeLevel')->where('active_time', $activeTime);
    }

    public function userWithdrawalAddress()
    {
        return $this->hasMany(UserWithdrawalAddress::class);
    }

    public function verifyOtp($authenticationCode)
    {
        $key = "OTPCode:{$this->id}:{$authenticationCode}";
        if (Cache::get($key) == $authenticationCode  && $authenticationCode !== null) {
            Cache::put($key, $authenticationCode, User::OTP_CACHE_LIVE_TIME);
            $result = 409;
            return $result;
        }
        $googleAuthenticator = new PHPGangsta_GoogleAuthenticator();
        $result = $googleAuthenticator->verifyCode($this->google_authentication, $authenticationCode, 0);
        if ($result) {
            if (Cache::get($key) !== $authenticationCode) {
                Cache::put($key, $authenticationCode, User::OTP_CACHE_LIVE_TIME);
            }
        }
        return $result;
    }

    public function verifySmsOtp($otp)
    {
        return $otp == Cache::get($this->getSmsOtpKey());
    }

    public function createSmsOtp()
    {
        $otp = Utils::generateRandomString(6, '0123456789');
        Cache::put($this->getSmsOtpKey(), $otp, 3*60);
        return $otp;
    }

    private function getSmsOtpKey()
    {
        return "sms_otp_{$this->id}";
    }

    public function getHiddenBankAccountNumber()
    {
        $accountNumber = $this->real_account_no;
        if (!$accountNumber) {
            return $accountNumber;
        }
        $length = strlen($accountNumber);
        if ($length > 4) {
            $accountNumber = substr($accountNumber, 0, 2) . str_repeat('*', $length - 4)
                . substr($accountNumber, $length - 2);
        }
        return $accountNumber;
    }

    public function referrered_users()
    {
        return $this->hasMany(User::class, 'referrer_id', 'id');
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function hasOTP()
    {
        return $this->securitySetting->otp_verified;
    }

    public function isEnableWhiteList()
    {
        return !! $this->userSetting()->where('key', 'whitelist')->value('value');
    }

    public function checkWhiteListAddress($address)
    {
        return !!$this->userWithdrawalAddress()->where('wallet_address', $address)
            ->where('is_whitelist', Consts::TRUE)->count();
    }

    public function isActive()
    {
        return $this->status === Consts::USER_ACTIVE;
    }

    public function isBot()
    {
        return $this->type === Consts::USER_TYPE_BOT;
    }

    public function isValid()
    {
        return !$this->isBot() && $this->isActive();
    }

    public function scopeFilter($query, $input)
    {
        if (isset($input['search'])) {
            $query->where(function ($query) use ($input) {
                $query->orWhere('name', "LIKE", "%{$input['search']}%");
                $query->orWhere('email', "LIKE", "%{$input['search']}%");
            });
        }
        return $query;
    }

    public function isReferrer($user_id)
    {
        return User::where('referrer_id', $user_id)->count();
    }

    public function getReferrerId($user_id)
    {
        return User::where('id', $user_id)->value('referrer_id');
    }

    public function getUserIsnotBot()
    {
        return User::where('type', '<>', 'bot');
    }

    public function getIsInfoRegisteredAttribute()
    {
        return $this->userInfo()->count();
    }

    public function getIsKycRegisteredAttribute()
    {
        return $this->kyc()->count();
    }

    public function getKycStatusAttribute()
    {
        return $this->kyc()->value('status') ?? 'unverified';
    }

    public function securitySetting()
    {
        return $this->hasOne(UserSecuritySetting::class, 'id');
    }

    public function connections()
    {
        return $this->hasMany(UserConnectionHistory::class);
    }

    public function kyc()
    {
        return $this->hasOne(UserKyc::class);
    }

    public function userInfo()
    {
        return $this->hasOne(UserInformation::class);
    }

    public function userSetting()
    {
        return $this->hasMany(UserSetting::class);
    }

    public function getLocale()
    {
        return $this->userSetting()->where('key', 'locale')->value('value') ?? App::getLocale();
    }

    public function userNotificationSettings()
    {
        return $this->hasMany(UserNotificationSetting::class);
    }

    public function notices()
    {
        return $this->belongsToMany(Notice::class);
    }

    public function news()
    {
        return $this->belongsToMany(News::class)->withPivot('is_read');
    }

    public function btcAccount()
    {
        return $this->hasOne(BtcAccount::class, 'id', 'id');
    }

    public function vouchers()
    {
        return $this->belongsToMany(Voucher::class);
    }

    /**
     * Query valid tokens
     *
     */
    public function queryValidTokens()
    {
        return $this->tokens()
            ->whereRevoked(Consts::FALSE)
            ->where('expires_at', '>', Carbon::now());
    }

    public function revokeAllToken($withoutThisSession = true)
    {
        $tokens = $this->queryValidTokens()->get();
        $current_token = $this->token();
        foreach ($tokens as $token) {
            if ($withoutThisSession && $token->id == $current_token->id) {
                continue;
            }
            $token->revoke();
        }
    }

    public function userAntiPhishingActiveLatest()
    {
        return $this->hasMany(UserAntiPhishing::class)->where('is_active', true)->orderByDesc('id');
    }

    public function affiliateTreeUsers() {
        return $this->hasMany(AffiliateTrees::class, 'user_id', 'id');
    }

    public function userRate() {
        return $this->hasOne(UserRates::class, 'id', 'id');
    }

    public function referrerUser() {
        return $this->belongsTo(User::class, 'referrer_id', 'id');
    }

    public function userSamsubKYC() {
        return $this->hasOne(UserSamsubKyc::class, 'user_id', 'id');
    }

    public function userDeviceRegisters() {
        return $this->hasMany(UserDeviceRegister::class, 'user_id', 'id');
    }

    public function affiliateTreesDown() {
        return $this->hasMany(AffiliateTrees::class, 'referrer_id', 'id');
    }

    public function affiliateTreesUp() {
        return $this->hasMany(AffiliateTrees::class, 'user_id', 'id');
    }

    public function transferHistory() {
        return $this->hasMany(TransferHistory::class, 'user_id', 'id');
    }

    public function transactions() {
        return $this->hasMany(Transaction::class, 'user_id', 'id');
    }

    public function sellerTransactions()
    {
        return $this->hasMany(OrderTransaction::class, 'seller_id', 'id');
    }

    public function buyerTransactions()
    {
        return $this->hasMany(OrderTransaction::class, 'buyer_id', 'id');
    }

    public function completeTransactions()
    {
        return $this->hasMany(CompleteTransaction::class, 'user_id', 'id');
    }

    public function reportTransactions()
    {
        return $this->hasMany(ReportTransaction::class, 'user_id', 'id');
    }

    public function AccountProfileSetting() {
        return $this->hasOne(AccountProfileSetting::class, 'user_id', 'id');
    }

    public function activityLogs() {
        return $this->morphMany(ActivityLog::class, 'object');
    }

    public function readNewsNotifications()
    {
        return $this->belongsToMany(NewsNotification::class, 'news_notification_users')
            ->withPivot('read_at')
            ->withTimestamps();
    }

    public function scopeNoDeposit($query) {
        return $query->whereDoesntHave('transactions', function ($q) {
            $q->where('amount', '>', 0);
        });
    }

}
