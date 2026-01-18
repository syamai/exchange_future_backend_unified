<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

use App\Consts;
use App\Http\Controllers\Admin\EnableWithdrawalSettingController;
use App\Http\Controllers\API\AddressManagerAPIController;
use App\Http\Controllers\API\AirdropAPIController;
use App\Http\Controllers\API\AmlSettingApiController;
use App\Http\Controllers\API\AmlTransactionApiController;
use App\Http\Controllers\API\Auth\LoginController;
use App\Http\Controllers\API\BetaTesterController;
use App\Http\Controllers\API\ChartAPIController;
use App\Http\Controllers\API\ChatbotController;
use App\Http\Controllers\API\BlogController;
use App\Http\Controllers\API\CoinCheckAPIController;
use App\Http\Controllers\API\CoinMarketCapAPIController;
use App\Http\Controllers\API\FaqController;
use App\Http\Controllers\API\NewsNotificationController;
use App\Http\Controllers\API\SocialNewsController;
use App\Http\Controllers\API\FavoriteAPIController;
use App\Http\Controllers\API\HotWalletAPIController;
use App\Http\Controllers\API\InquiryController;
use App\Http\Controllers\API\KRWDepositWithdrawController;
use App\Http\Controllers\API\MarketPriceChangesAPIController;
use App\Http\Controllers\API\MarketStatisticController;
use App\Http\Controllers\API\MasterdataAPIController;
use App\Http\Controllers\API\NewsAPIController;
use App\Http\Controllers\API\NoticeAPIController;
use App\Http\Controllers\API\NotificationAPIController;
use App\Http\Controllers\API\OrderAPIController;
use App\Http\Controllers\API\PriceAPIController;
use App\Http\Controllers\API\ReferralAPIController;
use App\Http\Controllers\API\ServiceCenterAPIController;
use App\Http\Controllers\API\SettingAPIController;
use App\Http\Controllers\API\SiteSettingAPIController;
use App\Http\Controllers\API\TransactionAPIController;
use App\Http\Controllers\API\UserAPIController;
use App\Http\Controllers\API\UserSettingAPIController;
use App\Http\Controllers\API\ValidateController;
use App\Http\Controllers\API\VoucherAPIController;
use App\Http\Controllers\API\WebhookController;
use App\Http\Controllers\API\ZendeskAPIController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\SpotMatchingEngineController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\PromController;
use App\Http\Middleware\AuthenticateUser;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\SMSVerificationController;
use App\Http\Controllers\API\PromotionController;
use App\Http\Controllers\API\ReferrerClientController;

$maxAttempts = env('THROTTLE_API_MARGIN_MAX_REQUESTS', 10);
$decaySeconds = env('THROTTLE_API_MARGIN_DURING_SECONDS', 1);
$middlewareThrottleMargin = "throttle_requests_per_second:{$maxAttempts},{$decaySeconds}";
$middlewareThrottleMarginDay = "throttle_requests_per_second:200000,86400";
if (env('ENABLE_CMC_API')) {
    //coin market cap
    Route::get('/summary', [OrderAPIController::class, 'getMarketSummary']);
    Route::get('/assets', [MasterdataAPIController::class, 'getAssets']);
    Route::get('/ticker', [PriceAPIController::class, 'getTicker']);
    Route::get('/trades/{pair}', [OrderAPIController::class, 'getRecentTradesForPair']);
    Route::get('/orderbook/{pair}', [OrderAPIController::class, 'getCmcOrderbook']);
    Route::get('/contracts', [OrderAPIController::class, 'getContractsSummary']);
    Route::get('/contract-specs', [OrderAPIController::class, 'getContractsSpecsSummary']);
}

Route::get('/healthcheck', function () {
    return true;
});

Route::group(['prefix' => 'engine'], function () {
    Route::get('send-init', [SpotMatchingEngineController::class, 'sendInit']);
	Route::get('test', [SpotMatchingEngineController::class, 'test']);
});

Route::middleware(['metric'])->get('/metric', [PromController::class, 'metric']);
Route::middleware(['metric'])->get('/metrics', [PromController::class, 'index']);

Route::get('/masterdata', [MasterdataAPIController::class, 'index']);
Route::post('/check-kyc', [UserAPIController::class, 'saveStatusKyc']);
Route::get('/vouchers/types-status', [VoucherAPIController::class, 'getTypesAndStatus']);
Route::get('/market-statistic', [MarketStatisticController::class, 'getMarketStatistic']);

Route::get('/prices/24h', [PriceAPIController::class, 'get24hPrices']);
Route::get('prices', [PriceAPIController::class, 'getPrices']);
Route::get('price', [PriceAPIController::class, 'getPrice']);
// Route::get('prices-history', 'API\PriceAPIController@getPricesHistory');
Route::get('/market-info', [PriceAPIController::class, 'getMarketInfo']);
Route::get('/market/hot-symbols', [MarketStatisticController::class, 'getHotSymbols']);
Route::get('/orders/order-book', [OrderAPIController::class, 'getOrderBook']);
Route::get('/orders/transactions/recent', [OrderAPIController::class, 'getRecentTransactions']);
Route::group(['prefix' => 'coin-makert-cap'], function () {
    // Route::get('ticker', 'API\CoinMarketCapAPIController@getTickers');
    Route::get('current-rate', [CoinMarketCapAPIController::class, 'getCurrentRate']);
    Route::get('current-curencies-rate', [CoinMarketCapAPIController::class, 'getCurrentCurrenciesRate']);
});
Route::get('/coin-check/btc-usd', [CoinCheckAPIController::class, 'getPriceBtcUsdExchanges']);
Route::get('symbols', [ChartAPIController::class, 'getAllSymbols']);
Route::get('market-price-changes', [MarketPriceChangesAPIController::class, 'getPriceChanges']);
Route::get('price-scope', [PriceAPIController::class, 'getPriceScopeIn24h']);
Route::get('/symbols/trending', [PriceAPIController::class, 'getTrendingSymbols']);
Route::get('/zendesk/articles/{categoryID}', [ZendeskAPIController::class, 'getArticles']);
Route::get('/support', [ZendeskAPIController::class, 'getSupportLogin']);

Route::get('notices/banners', [NoticeAPIController::class, 'getBannerNotices']);
Route::get('/get-show-remain-aml', [SettingAPIController::class, 'index']);
Route::get('/site-settings', [SiteSettingAPIController::class, 'index']);
Route::post('/voucher-future', [VoucherAPIController::class, 'addBalanceVoucherFuture']);

Route::post('/oauth/token', [
    LoginController::class, 'issueToken'
])->middleware(IPActive\Define::LOGIN_MIDDLEWARE, 'encrypt_pass', 'pre_login', AuthenticateUser::class, 'suf_login');

Route::post('/oauth/biometrics', [
    LoginController::class, 'loginBiometrics'
])->middleware(IPActive\Define::LOGIN_MIDDLEWARE, 'encrypt_pass', AuthenticateUser::class);

Route::post('/users', [RegisterController::class, 'registerViaApi'])->middleware(\IPActive\Define::REGISTER_MIDDLEWARE);
Route::get('/country/mobile-code', [SMSVerificationController::class, 'getMobileCode']);
Route::post('/send-phone-otp', [SMSVerificationController::class, 'sendOTP']);
Route::post('/verify-phone-otp', [SMSVerificationController::class, 'verifyOTP']);
Route::group(['middleware' => 'auth:api'], function () {
    Route::post('/confirm-phone-otp', [SMSVerificationController::class, 'confirmPhoneOTP']);
});
Route::post('/confirm-email', [RegisterController::class, 'confirmEmailViaApi']);

Route::post('/confirm-token', [ForgotPasswordController::class, 'checkExpiredResetPassword']);

Route::post('/reconfirm-email', [RegisterController::class, 'resendConfirmEmailViaApi'])
    ->middleware(\IPActive\Define::RESEND_CONFIRMATION_EMAIL_MIDDLEWARE);
Route::post('/reset-password', [ForgotPasswordController::class, 'sendResetLinkEmailViaApi'])
    ->middleware(\IPActive\Define::SEND_RESET_PASSWORD_MIDDLEWARE);
Route::post('/execute-reset-password', [ResetPasswordController::class, 'resetViaApi']);

Route::get('/qr-code/generate', [UserAPIController::class, 'generateQRcodeLogin']);
Route::post('/qr-code/check', [UserAPIController::class, 'checkQRcodeLogin']);
Route::middleware(['api', 'auth:api'])->post('/qr-code/scan', [UserAPIController::class, 'mobileScanLogin']);

Route::middleware(['api_webview', 'auth:api'])->get('/user', [UserAPIController::class, 'getCurrentUser']);

Route::group(['middleware' => ['api_webview', 'auth:api']], function () {
    Route::post('create-user-qrcode', [UserAPIController::class, 'createUserQrcode']);
    Route::get('/user-referral-commission', [UserAPIController::class, 'getUserReferralCommission']);
    Route::get('/user', [UserAPIController::class, 'getCurrentUser']);
    Route::get('/get-total', [ReferralAPIController::class, 'getTotalReferrer']);
    Route::get('setting-referral', [ReferralController::class, 'getReferralSetting']);
    Route::get('get-user-referral-friends', [UserAPIController::class, 'getUserReferralFriends']);
    Route::get('get-all-referrer', [UserAPIController::class, 'getAllReferrer']);
    Route::get('trading/limits', [OrderAPIController::class, 'getTradingLimits']);

    // New Referral Page Features
    Route::prefix('referral')->group(function () {
        Route::prefix('management')->group(function () {
            Route::get('overview', [ReferralAPIController::class, 'getReferralOverview']);
            Route::get('list', [ReferralAPIController::class, 'getReferralList']);
            Route::get('list/export', [ReferralAPIController::class, 'exportReferralList']);
        });
        // Commission Routes
        Route::prefix('commission')->group(function () {
            Route::get('overview', [ReferralAPIController::class, 'getCommissionOverview']);
            Route::get('daily-trends', [ReferralAPIController::class, 'getCommissionDailyTrends']);
            Route::get('monthly-trends', [ReferralAPIController::class, 'getCommissionMonthlyTrends']);
            Route::get('history', [ReferralAPIController::class, 'getCommissionHistory']);
            Route::get('history/export', [ReferralAPIController::class, 'exportCommissionHistory']);

            // Commission Withdrawal Features
            Route::get('withdrawal-history', [ReferralAPIController::class, 'getWithdrawalHistory']);
            Route::post('withdraw', [ReferralAPIController::class, 'withdrawCommission']);
        });
    });
});

Route::group(['middleware' => 'auth:api', 'prefix' => 'notifications'], function () {
    Route::get('unread', [NotificationAPIController::class, 'getUnreadNotifications']);
    Route::put('read', [NotificationAPIController::class, 'markAllAsRead']);
});

Route::post('/webhook/sotatek', [WebhookController::class, 'onReceiveTransaction'])->middleware('auth.webhook');
Route::get('/webhook/get-transaction-info', [WebhookController::class, 'getTransaction'])->middleware('auth.webhook');
Route::get('/webhook/get-balances', [WebhookController::class, 'getAccountBalances'])->middleware('auth.webhook');

Route::post('/webhook/send-email', [WebhookController::class, 'sendEmailMarketing'])->middleware('auth.webhook');
Route::get('/webhook/get-accounts', [WebhookController::class, 'getInfoAccounts'])->middleware('auth.webhook');

Route::post('device/{code}', [UserAPIController::class, 'grantDevicePermission']);
Route::post('/verify-anti-phishing/{code}', [UserAPIController::class, 'verifyAntiPhishing']);
Route::post('/reconfirm-email-anti-phishing', [UserAPIController::class, 'resendConfirmEmailAntiPhishing']);
Route::get('/check-api-key', [LoginController::class, 'getAccessToken'])->middleware('check_api_key');

Route::group(['middleware' => 'auth:api', 'prefix' => 'spot'], function () {
    Route::get('balance/{currency}', [UserAPIController::class, 'getDetailsUserSpotBalance']);
});

Route::get('/pair-coin-settings', [SettingAPIController::class, 'getPairCoinSetting']);
Route::post('test-encrypt-data', [ValidateController::class, 'testEncryptData']);

Route::post('samsub/webhook', [UserAPIController::class, 'webhookSamsubKyc']);

Route::group(['middleware' => 'auth:api'], function () {
    Route::post('register-beta-tester', [BetaTesterController::class, 'registerBetaTester']);
    Route::get('user-pair-trading-setting', [UserAPIController::class, 'getUserPairTradingSetting']);
    Route::get('/change-wallet-amal-fee', [UserAPIController::class, 'changeWalletAmalFee']);
    Route::post('/user/anti-phishing', [UserAPIController::class, 'changeAntiPhishing']);

    Route::post('convert-small-balance', [UserAPIController::class, 'convertSmallBalance']);
    Route::post('transfer-balance', [UserAPIController::class, 'transferBalance']);
    Route::get('balances/{store?}', [UserAPIController::class, 'getCurrentUserBalance']);
    Route::get('balance/{currency}', [UserAPIController::class, 'getDetailsUserBalance']);
    Route::get('balance-usd/{currency}', [UserAPIController::class, 'getDetailsUserUsdBalance']);

    //    Route::get('reset-password/', 'API\ForgotPasswordController@checkResetPassword');

    Route::get('security-settings', [UserAPIController::class, 'getSecuritySettings']);
    Route::get('user-notification-settings', [UserAPIController::class, 'getUserNotificationSettings']);
    Route::get('user-settings', [UserAPIController::class, 'getUserSettings']);
    Route::get('user-kyc-old', [UserAPIController::class, 'getUserKyc']);
    Route::get('user-kyc', [UserAPIController::class, 'getUserSamsubKyc']);
    Route::post('user-kyc', [UserAPIController::class, 'startUserSamsubKyc']);

    Route::put('user-update-fake-name', [UserAPIController::class, 'updateUserFakeName']);
    Route::put('user-whitelist', [UserAPIController::class, 'changeWhiteListSetting']);
    Route::get('top-user-referral-commission', [UserAPIController::class, 'getTopUserReferralCommission']);
    Route::get('order-book-settings', [UserAPIController::class, 'getOrderBookSettings']);
    Route::put('order-book-settings', [UserAPIController::class, 'updateOrderBookSettings']);
    Route::get('favorites', [UserAPIController::class, 'getFavorite']);
    Route::post('favorites', [UserAPIController::class, 'insertFavorite']);
    Route::delete('favorites/{id}', [UserAPIController::class, 'deleteFavorite']);
    Route::post('reorder-favorites', [UserAPIController::class, 'reorderFavorites']);

    Route::get('devices', [UserAPIController::class, 'getDeviceRegister']);
    Route::get('user-devices', [UserAPIController::class, 'getUserDevice']);
    Route::put('restrict-mode', [UserAPIController::class, 'updateRestrictMode']);
    Route::delete('device/{id}', [UserAPIController::class, 'deleteDevice']);
    Route::get('connections', [UserAPIController::class, 'getConnections']);

    Route::get('key-google-authen', [UserAPIController::class, 'getKeyGoogleAuthen']);
    Route::put('add-security-setting-otp', [UserAPIController::class, 'addSecuritySettingOtp']);
    Route::get('verify-google-authenticator', [UserAPIController::class, 'verifyCode']);
    Route::get('purchase-amount', [UserAPIController::class, 'getPurchaseAmounts']);

    Route::get('withdrawal-networks', [UserAPIController::class, 'getNetworkWithdrawalAddress']);
    Route::get('withdrawal-address', [UserAPIController::class, 'getWithdrawalAddress']);

    Route::get('withdrawals-address', [UserAPIController::class, 'getWithdrawalsAddress']);
    Route::post('withdrawal-address', [UserAPIController::class, 'updateOrCreateWithdrawalAddress']);
    Route::put('deposit-address', [UserAPIController::class, 'createDepositAddress'])->name('deposit.create-address');
    Route::get('deposit-networks', [UserAPIController::class, 'getNetworkAddress'])->name('deposit.get-network');
    Route::get('deposit-address', [UserAPIController::class, 'getDepositAddress'])->name('deposit.get-address');
    Route::put('change-password', [UserAPIController::class, 'changePassword']);
    Route::delete('google-auth', [UserAPIController::class, 'delGoogleAuth']);
    Route::put('locale', [UserAPIController::class, 'updateOrCreateUserLocale']);
    Route::put('device-token', [UserAPIController::class, 'updateOrCreateDeviceToken']);
    Route::get('phone_verification_data', [UserAPIController::class, 'getPhoneVerificationData']);
    Route::put('bank-account', [UserAPIController::class, 'verifyBankAccount']);
    // Route::post('send-sms-otp', 'API\UserAPIController@sendSmsOtp');
    Route::get('withdrawal-limit', [UserAPIController::class, 'getWithdrawalLimitBTC']);
    Route::post('create-identity', [UserAPIController::class, 'createIdentity']);
    Route::post('otp-verify', [UserAPIController::class, 'otpVerify']);
    Route::delete('del-recovery-code-with-auth', [UserAPIController::class, 'delRecoveryCodeWithAuth']);
    Route::get('user/symbols-setting', [UserSettingAPIController::class, 'getSymbolSettings']);
    Route::post('user/symbols-setting', [UserSettingAPIController::class, 'updateSymbolSettings']);
    Route::put('user/use-fake-name', [UserSettingAPIController::class, 'useFakeName']);
    Route::post('change-email-notification', [UserAPIController::class, 'changeEmailNotification']);
    Route::post('change-aml-pay', [UserAPIController::class, 'changeAmlPay']);

    Route::get('/referral/friends/export', [ReferralController::class, 'exportToCSVReferralFriends']);
    Route::get('/referral/commission/export', [ReferralController::class, 'exportCSVCommissionHistory']);

    Route::post('change-telegram-notification', [UserAPIController::class, 'changeTelegramNotification']);

    Route::get('/user-withdrawal-setting', [EnableWithdrawalSettingController::class, 'getWithdrawSetting']);
    Route::get('/get-dividend-settings', [AirdropAPIController::class, 'getAirdropSetting']);
    Route::post('/add-biometrics', [UserAPIController::class, 'addBiometrics']);

    Route::get('/inquiry/type', [InquiryController::class, 'getInquiryType']);
    Route::get('/inquiries', [InquiryController::class, 'getInquiries']);
    Route::post('/inquiries', [InquiryController::class, 'insertInquiries']);
    Route::get('/inquiry/{id}', [InquiryController::class, 'getInquiry']);
});

Route::group(['prefix' => 'news-notifications'], function () {
    Route::get('/categories', [NewsNotificationController::class, 'getCategories']);
    Route::get('/', [NewsNotificationController::class, 'getNewsNotifications']);
    Route::get('/unread-count', [NewsNotificationController::class, 'getUnreadCount']);
    Route::middleware('auth:api')->group(function () {
        Route::post('/{id}/read', [NewsNotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NewsNotificationController::class, 'markAllAsRead']);
    });
});

Route::group(['prefix' => 'social'], function () {
    Route::get('/', [SocialNewsController::class, 'getListSocialNews']);
    Route::get('/pins', [SocialNewsController::class, 'getListPinSocialNews']);
});

Route::group(['prefix' => 'faq'], function () {
    Route::get('/categories', [FaqController::class, 'getCategories']);
    Route::get('/faqs', [FaqController::class, 'getFaqs']);
});

Route::group(['prefix' => 'chatbot'], function () {
    Route::get('/types', [ChatbotController::class, 'getTypes']);
    Route::get('/categories', [ChatbotController::class, 'getCategories']);
    Route::get('/', [ChatbotController::class, 'getData']);
});

Route::group(['prefix' => 'blog'], function () {
    Route::get('/categories', [BlogController::class, 'getCategories']);
    Route::get('/pins', [BlogController::class, 'getPins']);
    Route::get('/', [BlogController::class, 'getData']);
    Route::get('/{id}', [BlogController::class, 'getDetail']);
	Route::get('/slug/{slug}', [BlogController::class, 'getDetailSlug']);
    Route::get('/{id}/related', [BlogController::class, 'getRelated']);
});

Route::group(['prefix' => 'krw'], function () {
    Route::get('/setting', [KRWDepositWithdrawController::class, 'getSettings']);
    Route::group(['middleware' => 'auth:api'], function () {
        Route::get('/bank-names', [KRWDepositWithdrawController::class, 'getBankNames']);
        Route::get('/bank-accounts', [KRWDepositWithdrawController::class, 'getBankAccounts']);
        Route::get('/transactions', [KRWDepositWithdrawController::class, 'getTransactions']);
        Route::post('/deposit', [KRWDepositWithdrawController::class, 'depositKRW']);
        Route::post('/withdraw', [KRWDepositWithdrawController::class, 'withdrawKRW']);
    });

});

Route::group(['middleware' => 'auth:api', 'prefix' => 'orders'], function () {
    Route::put('cancel-all', [OrderAPIController::class, 'cancelAll'])->name('orders.cancel-all');
    Route::put('cancel-by-type', [OrderAPIController::class, 'cancelByType'])->name('orders.cancel-by-type');

    Route::put('{id}/cancel', [OrderAPIController::class, 'cancel'])->name('orders.cancel');
    Route::put('{id}/replace', [OrderAPIController::class, 'replace'])->name('orders.replace');
    //    Route::get('{id}/children', 'API\OrderAPIController@getChildOrders');

    Route::get('transactions/export', [OrderController::class, 'exportToCSVOrderHistory']);
    Route::get('transactions', [OrderAPIController::class, 'getTransactionHistory']);
    Route::get('detail/{id}', [OrderAPIController::class, 'getOrderDetail']);
    Route::get('pending', [OrderAPIController::class, 'getOrderPending']);
    Route::get('pending-all', [OrderAPIController::class, 'getOrderPendingAll']);
    Route::get('user-order-book', [OrderAPIController::class, 'getUserOrderBook']);
    Route::post('', [OrderAPIController::class, 'store'])->name('orders.store');
    Route::get('user-transactions', [OrderAPIController::class, 'getUserTransactions']);
    Route::get('trading-histories', [OrderAPIController::class, 'getTradingHistories']);
    Route::get('trade-history/export', [OrderController::class, 'exportCSVTradeHistory']);
    Route::get('{currency}/{status}', [OrderAPIController::class, 'getOrders']);
    Route::get('market_fee', [OrderAPIController::class, 'getMarketFee']);
});

Route::group(['middleware' => 'auth:api', 'prefix' => 'address'], function () {
    Route::post('/', [AddressManagerAPIController::class, 'insertWalletAddress']);
    Route::put('/', [AddressManagerAPIController::class, 'updateWalletsWhiteList']);
    Route::delete('/', [AddressManagerAPIController::class, 'removeWalletsAddress']);
});

// Transaction (deposit, withdraw)
Route::group(['middleware' => 'auth:api', 'prefix' => 'transactions'], function () {
    Route::get('export', [WalletController::class, 'exportExcel']);
    Route::get('{currency}/export', [WalletController::class, 'exportExcel']);
    Route::get('withdraw-daily-usd', [TransactionAPIController::class, 'getUsdWithdrawDaily']);
    Route::get('stats', [TransactionAPIController::class, 'getUserTransactions']);
    Route::get('usd', [TransactionAPIController::class, 'getUsdTransactionHistory']);

    Route::post('withdraw-usd', [TransactionAPIController::class, 'withdrawUsd']);
    Route::get('withdraw-daily', [TransactionAPIController::class, 'getWithdrawDaily']);
    Route::post('deposit-usd', [TransactionAPIController::class, 'depositUsd']);
    Route::put('deposit/cancel-usd/{transactionId}', [TransactionAPIController::class, 'cancelUsdDepositTransaction']);
    Route::get('withdraw/total-pending-withdraw', [TransactionAPIController::class, 'getTotalPendingWithdraw']);
    Route::get('withdraw/total-usd-pending-withdraw', [TransactionAPIController::class, 'getTotalUsdPendingWithdraw']);
    Route::get('{currency?}', [TransactionAPIController::class, 'getHistory']);
});

// Service (contact)
Route::group(['middleware' => 'auth:api', 'prefix' => 'contact'], function () {
    Route::post('/', [ServiceCenterAPIController::class, 'sendContact']);
});
Route::group(['middleware' => 'auth:api', 'prefix' => 'notices'], function () {
    Route::get('/', [ServiceCenterAPIController::class, 'getNotices']);
    Route::get('/{id}', [ServiceCenterAPIController::class, 'getNotice']);
});

Route::group(['middleware' => 'api', 'prefix' => 'news'], function () {
    Route::get('/', [NewsAPIController::class, 'getNews']);
    Route::get('/count-unread', [NewsAPIController::class, 'getCountUnRead']);
    Route::get('/get-user-news-info', [NewsAPIController::class, 'getUserNewsInfo']);
    Route::get('/{id}', [NewsAPIController::class, 'getNewsRecord']);
    Route::post('/{id}/change-status/{status}', [NewsAPIController::class, 'changeNewsStatus']);
});

Route::group(['prefix' => 'chart'], function () {
    Route::get('bars', [ChartAPIController::class, 'getBars']);
    Route::get('time', [ChartAPIController::class, 'getServerTime']);
    Route::get('config', [ChartAPIController::class, 'getConfig']);
    Route::get('symbols', [ChartAPIController::class, 'getSymbols']);
});

Route::group(['middleware' => 'auth:api', 'prefix' => 'prices'], function () {
    Route::get('{market}/{currency}/{coin}', [PriceAPIController::class, 'getExternalPrice']);
});

Route::delete('disable-otp-authentication', [UserAPIController::class, 'disableOtpAuthentication']);

Route::group(['middleware' => ['auth:api', 'auth.message']], function () {
    Route::get('singed-endpoint', function () {
        return 'passed';
    });
});

Route::group(['middleware' => 'auth:api', 'prefix' => 'favorites'], function () {
    Route::get('/', [FavoriteAPIController::class, 'getList']);
    Route::get('/get-list-favorite', [FavoriteAPIController::class, 'getListFavorite']);
    Route::post('/', [FavoriteAPIController::class, 'create']);
    Route::post('/add-all', [FavoriteAPIController::class, 'addAll']);
    Route::delete('/', [FavoriteAPIController::class, 'delete']);
});


Route::group(['prefix' => 'hotwallet'], function () {
    Route::post('create-receive-address', [HotWalletAPIController::class, 'createReceiveAddress']);
});


Route::resource('aml-settings', AmlSettingApiController::class)->only(['index']);

Route::group(['middleware' => 'auth:api'], function () {
    Route::resource('aml-transactions', AmlTransactionApiController::class)->only(['index']);

    Route::get('aml-cash-back', [AmlTransactionApiController::class, 'getCashBack']);
    // Route::get('get-my-bonus', 'API\AmlSettingApiController@getBonus')->name('aml.my.bonus');
    Route::get('aml-export', [AmlTransactionApiController::class, 'exportExcel'])->name('aml.export');
    Route::get('total-supply', [AirdropAPIController::class, 'getTotalAMAL']);
});

Route::get('aml-settings', [\App\Http\Controllers\API\AmlSettingApiController::class, 'index']);

Route::get('customer-info/', [\App\Http\Controllers\API\UserAPIController::class, 'getCustomerInformation']);

Route::get('coin-for-sale-point', [\App\Http\Controllers\API\CoinMarketCapAPIController::class, 'getForSalePoint']);
Route::get('/get-auth-code', [\App\Http\Controllers\API\LineNotifyAPIController::class, 'getAuthCode']);
Route::get('/get-auth-code-for-mobile', [\App\Http\Controllers\API\LineNotifyAPIController::class, 'getAuthCodeForMobile']);
Route::post('/encrypt-id', [\App\Http\Controllers\API\LineNotifyAPIController::class, 'encryptId']);
Route::post('change-line-notification', [\App\Http\Controllers\API\UserAPIController::class, 'changeLineNotification']);
Route::post('startBot', [\App\Http\Controllers\API\BotTelegramAPIController::class, 'startBot']);
Route::get('get-leaderboard-setting', [\App\Http\Controllers\Admin\LeaderboardController::class, 'getLeaderboardSetting']);
Route::get('trading-volume-ranking', [\App\Http\Controllers\Admin\LeaderboardController::class, 'getTopTradingVolumeRankingByUser']);
Route::get('/client-ip', [\App\Http\Controllers\API\SiteSettingAPIController::class, 'getClientIp']);
Route::get('/location-info', [\App\Http\Controllers\API\SiteSettingAPIController::class, 'getLocationInfo']);

Route::get('validate-blockchain-address', [ValidateController::class, 'blockchainAddress']);

// Chainup APIs
Route::get('order-transaction-latest', [\App\Http\Controllers\API\OrderTransactionAPIController::class, 'getLatest']);

Route::group(['middleware' => 'auth:api'], function () {
    Route::get('my-order-transactions', [\App\Http\Controllers\API\OrderTransactionAPIController::class, 'getAllTradeHistory']);
    Route::get('get-trades-by-order-id/{id}', [\App\Http\Controllers\API\OrderTransactionAPIController::class, 'getByOrderId']);
});

Route::get('captcha/pre-verify', [\App\Http\Controllers\API\GeetestAPIController::class, 'preVerify']);

Route::get('insurance', [\App\Http\Controllers\API\InsuranceAPIController::class, 'getInsuranceFund']);


Route::group(['prefix' => 'transfer', 'middleware' => 'auth:api'], function () {
    Route::post('', [\App\Http\Controllers\API\TransferController::class, 'transfer']);
    Route::get('/history', [\App\Http\Controllers\API\TransferController::class, 'getTransferHistory']);
});

Route::post('transfer-future', [\App\Http\Controllers\API\TransferController::class, 'transferFuture'])->middleware('auth.future');
Route::post('/referral-future', [\App\Http\Controllers\API\TransferController::class, 'referralFuture'])->middleware('auth.future');

Route::group(['middleware' => 'auth:api', 'prefix' => 'vouchers'], function () {
    Route::post('/claim', [\App\Http\Controllers\API\VoucherController::class, 'claim']);
    Route::post('/claim/future', [\App\Http\Controllers\API\VoucherController::class, 'claimFuture']);
    Route::get('/get-list-voucher', [VoucherAPIController::class, 'getListVoucher']);
    Route::get('/get-trading-volume', [VoucherAPIController::class, 'getUserTradingVolume']);
});

Route::group(['middleware' => 'auth:api', 'prefix' => 'pnl'], function () {
    Route::get('/', [\App\Http\Controllers\API\PnlController::class, 'getPnlUser']);
});

Route::get('/get-image-mail', [SiteSettingAPIController::class, 'getImageMail']);

if (env('MARK_PRICE_DEBUG', false)) {
    Route::get('debug/mark-price/{symbol}/{price?}', function ($symbol, $price = null) {
        if ($price) {
            \Cache::forever("DEBUG_MARK_PRICE_{$symbol}", $price);
        } else {
            \Cache::forget("DEBUG_MARK_PRICE_{$symbol}", $price);
        }
        return \Cache::get("DEBUG_MARK_PRICE_{$symbol}");
    });
}

Route::get('get-client-secret', [SettingAPIController::class, 'getClients']);

Route::group(['prefix' => 'promotions'], function () {
    Route::get('/', [PromotionController::class, 'getPromotions']);
    Route::get('/{promotion}', [PromotionController::class, 'getPromotionDetails']);
});


Route::group(['middleware' => 'auth:api', 'prefix' => 'referrer'], function () {
    Route::get('tier/progress', [ReferrerClientController::class, 'tierProgress']);
    Route::get('recent/activities', [ReferrerClientController::class, 'recentActivities']);
    Route::get('recent/activities/export', [ReferrerClientController::class, 'recentActivitiesExport']);
    Route::get('commission/daily', [ReferrerClientController::class, 'dailyCommission']);
    Route::get('commission/ranking', [ReferrerClientController::class, 'rankingListWeekSub']);
    Route::get('leaderboard', [ReferrerClientController::class, 'leaderboard']);
});