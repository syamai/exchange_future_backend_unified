<?php
//use Illuminate\Support\Facades\Route;

// use Symfony\Component\Routing\Annotation\Route;
// use App\Http\Controllers\Admin\ColdWalletSettingController;
use App\Http\Controllers\Admin\VoucherController;
use App\Http\Controllers\Admin\SpotShowController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\PlayerReportRealController;
use App\Http\Controllers\Admin\ReferralController;
use App\Http\Controllers\Admin\UsersStatisticsOverviewController;

Route::get('/login', 'Admin\LoginController@showLoginForm')->middleware('admin.guest');

Route::post('/logout', 'Admin\LoginController@logout');

Route::post('/login', 'Admin\LoginController@login')->name("adminLogin");
Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::group(['prefix' => 'account'], function () {
        Route::get('/', 'Admin\AccountController@index');
        Route::get('/{id}', 'Admin\AccountController@index');

        Route::get('/{id}/logs/balance', 'Admin\AccountController@index');
        Route::get('/{id}/balance/spot', 'Admin\AccountController@index');
        Route::get('/{id}/transactions/withdraw', 'Admin\AccountController@index');
        Route::get('/{id}/transactions/deposit', 'Admin\AccountController@index');
        Route::get('/{id}/transactions/transfer', 'Admin\AccountController@index');

        Route::get('/{id}/affiliate/tree/down', 'Admin\AccountController@index');
        Route::get('/{id}/affiliate/tree/up', 'Admin\AccountController@index');

        Route::get('/{id}/history/activity', 'Admin\AccountController@index');

        Route::get('/{id}/spot/orders/open', 'Admin\AccountController@index');
        Route::get('/{id}/spot/orders/history', 'Admin\AccountController@index');
        Route::get('/{id}/spot/trade/history', 'Admin\AccountController@index');
        Route::get('/{id}/spot/setting/profile', 'Admin\AccountController@index');
        Route::post('/{id}/spot/setting/profile', 'Admin\AccountController@index');

        Route::post('/{id}', 'Admin\AccountController@index');
        Route::get('/export', 'Admin\AccountController@index');
        Route::get('/params/{key}', 'Admin\AccountController@index');

        Route::get('/kyc/{id}', 'Admin\AccountController@index');
        Route::get('/kyc/params/{key}', 'Admin\AccountController@index');
        Route::post('/kyc/{id}', 'Admin\AccountController@index');

        Route::get('/coin/{type}/trade/spot', 'Admin\AccountController@coinPairTradeSpot');

        Route::post('/{id}/stop-otp-authentication', 'Admin\AccountController@disableOtpAuthentication');
		Route::post('/{id}/approved', 'Admin\AccountController@adminApproved');
		Route::post('/{id}/denied', 'Admin\AccountController@adminDenied');
		Route::get('/statistics/kyc', 'Admin\AccountController@statisticsKYC');

    });
    Route::group(['prefix' => 'dashboard'], function () {
        Route::get('/logs/account/history', 'Admin\DashboardController@logsAccountsHistory');
        Route::get('/logs/transactions/history/{params?}', 'Admin\DashboardController@logsTransactionsHistory');
        //Route::get('/logs/transactions/history/{currency}', 'Admin\DashboardController@logsTransactionsHistory');
    });
    Route::group(['prefix' => 'spot'], function () {
        Route::get('/orderbook', 'Admin\SpotShowController@getlist');
        Route::get('/orderbook/{params}', 'Admin\SpotShowController@getParamsOrderbook');

        Route::get('/orders/history', 'Admin\SpotShowController@getOrdersHistory');
        Route::get('/orders/history/export', 'Admin\SpotShowController@exportOrdersHistory');
        Route::get('/orders/history/{params}', 'Admin\SpotShowController@getParamsOrdersHistory');

        Route::get('/orders/trade/history', 'Admin\SpotShowController@tradeOrdersHistory');
        Route::get('/orders/trade/history/export', 'Admin\SpotShowController@exportOrdersTradeHistory');
        Route::get('/orders/trade/history/{params}', 'Admin\SpotShowController@getParamsOrdersTradeHistory');

        Route::get('/orders/open', 'Admin\SpotShowController@OrdersOpen');
        Route::get('/orders/open/export', 'Admin\SpotShowController@exportOrdersOpen');
        Route::get('/orders/open/{params}', 'Admin\SpotShowController@getParamsOrdersOpen');
        Route::put('/order/{id}/cancel', 'Admin\SpotShowController@cancelOrder');

        Route::get('/transactions/withdraw', 'Admin\SpotShowController@withdraw');
        Route::get('/transactions/withdraw/export', 'Admin\SpotShowController@exportWithdraw');
        Route::get('/transactions/withdraw/{params}', 'Admin\SpotShowController@getParamswithdraw');
        Route::get('/transactions/deposit', 'Admin\SpotShowController@deposit');
        Route::get('/transactions/deposit/export', 'Admin\SpotShowController@exportDeposit');
        Route::get('/transactions/deposit/{params}', 'Admin\SpotShowController@getParamsdeposit');
    });

    Route::group(['prefix' => 'fund'], function () {
        Route::post('deposit', 'Admin\AdminController@adminDeposit');
    });

    Route::get('/inquiry/type', 'Admin\InquiryController@getInquiryType');
    Route::get('/inquiries', 'Admin\InquiryController@getInquiries');
    Route::get('/inquiry/{id}', 'Admin\InquiryController@getInquiry');
    Route::put('/inquiry/{id}', 'Admin\InquiryController@updateInquiry');

    Route::group(['prefix' => 'faq'], function () {
        Route::get('/categories', 'Admin\FaqController@getCategories');
        Route::post('/categories', 'Admin\FaqController@storeCategories');
        Route::get('/category/{id}', 'Admin\FaqController@getCategory');
        Route::put('/category/{id}', 'Admin\FaqController@updateCategory');
        Route::delete('/category/{id}', 'Admin\FaqController@destroyCategory');
        Route::delete('/sub-category/{id}', 'Admin\FaqController@destroySubCategory');
        Route::get('/faqs', 'Admin\FaqController@getFaqs');
        Route::post('/faqs', 'Admin\FaqController@storeFaqs');
        Route::get('/{id}', 'Admin\FaqController@getFaq');
        Route::put('/{id}', 'Admin\FaqController@updateFaq');
        Route::delete('/{id}', 'Admin\FaqController@destroyFaq');

    });

    Route::group(['prefix' => 'news-notifications'], function () {
        Route::get('/categories', 'Admin\NewsNotificationController@getCategories');
        Route::post('/categories', 'Admin\NewsNotificationController@storeCategories');
        Route::get('/category/{id}', 'Admin\NewsNotificationController@getCategory');
        Route::put('/category/{id}', 'Admin\NewsNotificationController@updateCategory');
        Route::delete('/category/{id}', 'Admin\NewsNotificationController@destroyCategory');
        Route::get('/', 'Admin\NewsNotificationController@getNewsNotifications');
        Route::post('/', 'Admin\NewsNotificationController@storeNewsNotifications');
        Route::get('/{id}', 'Admin\NewsNotificationController@getNewsNotification');
        Route::put('/{id}', 'Admin\NewsNotificationController@updateNewsNotification');
        Route::put('/{id}/status', 'Admin\NewsNotificationController@updateStatusNewsNotification');
        Route::delete('/{id}', 'Admin\NewsNotificationController@destroyNewsNotification');
    });

    Route::group(['prefix' => 'social'], function () {
        Route::get('/', 'Admin\SocialNewsController@getListSocialNews');
        Route::get('/pins', 'Admin\SocialNewsController@getListPinSocialNews');
        Route::get('/pins/count', 'Admin\SocialNewsController@getCountPinSocialNews');
        Route::post('/', 'Admin\SocialNewsController@storeSocialNews');
        Route::get('/{id}', 'Admin\SocialNewsController@getSocialNews');
        Route::post('/{id}', 'Admin\SocialNewsController@updateSocialNews');
        Route::put('/{id}/status', 'Admin\SocialNewsController@updateStatusSocialNews');
        Route::put('/{id}/pin', 'Admin\SocialNewsController@pinSocialNews');
        Route::put('/{id}/unpin', 'Admin\SocialNewsController@unpinSocialNews');
        Route::delete('/{id}', 'Admin\SocialNewsController@destroySocialNews');
    });

    Route::group(['prefix' => 'chatbot'], function () {
        Route::get('/types', 'Admin\ChatBotController@getTypes');
        Route::get('/categories', 'Admin\ChatBotController@getCategories');
        Route::post('/categories', 'Admin\ChatBotController@storeCategories');
        Route::get('/category/{id}', 'Admin\ChatBotController@getCategory');
        Route::put('/category/{id}', 'Admin\ChatBotController@updateCategory');
        Route::put('/category/{id}/status', 'Admin\ChatBotController@updateStatusCategory');
        Route::delete('/category/{id}', 'Admin\ChatBotController@destroyCategory');
        Route::delete('/sub-category/{id}', 'Admin\ChatBotController@destroySubCategory');

        Route::get('/chatbots', 'Admin\ChatBotController@getChatBots');
        Route::post('/', 'Admin\ChatBotController@storeChatBot');
        Route::get('/{id}', 'Admin\ChatBotController@getChatBot');
        Route::put('/{id}', 'Admin\ChatBotController@updateChatBot');
        Route::put('/{id}/status', 'Admin\ChatBotController@updateStatusChatBot');
        Route::delete('/{id}', 'Admin\ChatBotController@destroyChatBot');
    });

    Route::group(['prefix' => 'blog'], function () {
        Route::get('/categories', 'Admin\BlogController@getCategories');
        Route::post('/categories', 'Admin\BlogController@storeCategories');
        Route::get('/category/{id}', 'Admin\BlogController@getCategory');
        Route::put('/category/{id}', 'Admin\BlogController@updateCategory');
        Route::put('/category/{id}/status', 'Admin\BlogController@updateStatusCategory');
        Route::delete('/category/{id}', 'Admin\BlogController@destroyCategory');

        Route::get('/blogs', 'Admin\BlogController@getBlogs');
        Route::get('/pins', 'Admin\BlogController@getPinBlogs');
        Route::post('/blogs', 'Admin\BlogController@storeBlog');
        Route::get('/{id}', 'Admin\BlogController@getBlog');
        Route::post('/{id}', 'Admin\BlogController@updateBlog');
        Route::put('/{id}/status', 'Admin\BlogController@updateStatusBlog');
        Route::put('/{id}/pin', 'Admin\BlogController@pinBlog');
        Route::put('/{id}/unpin', 'Admin\BlogController@unpinBlog');
        Route::delete('/{id}', 'Admin\BlogController@destroyBlog');
    });

    Route::group(['prefix' => 'image'], function () {
        Route::post('/upload', 'Admin\FileController@uploadImage');
    });

	Route::group(['prefix' => 'utm'], function () {
		Route::get('/', 'Admin\UTMController@getList');
	});

    Route::group(['prefix' => 'activity-logs'], function () {
        Route::get('/', 'Admin\ActivityLogController@index');
    });

    Route::group(['prefix' => 'krw'], function () {
        Route::get('/bank-names', 'Admin\KRWDepositWithdrawController@getBankNames');
        Route::get('/bank-accounts', 'Admin\KRWDepositWithdrawController@getBankAccounts');
        Route::post('/bank-accounts', 'Admin\KRWDepositWithdrawController@storeBankAccount');
        Route::put('/bank-account/{id}', 'Admin\KRWDepositWithdrawController@updateBankAccount');
        Route::delete('/bank-account/{id}', 'Admin\KRWDepositWithdrawController@destroyBankAccount');

        Route::get('/setting', 'Admin\KRWDepositWithdrawController@getSettings');
        Route::put('/setting', 'Admin\KRWDepositWithdrawController@updateSettings');
        Route::get('/transactions', 'Admin\KRWDepositWithdrawController@getTransactions');
        Route::put('/confirm-transaction', 'Admin\KRWDepositWithdrawController@confirmKRWTransaction');
        Route::put('/reject-transaction', 'Admin\KRWDepositWithdrawController@rejectKRWTransaction');
    });



    Route::post('/auth', 'Admin\LoginController@authFuture');
    //    Route::put('/masterdata/update-masterdata', 'API\MasterdataAPIController@updateMasterdata')->name('admin.master-data.update');
    // Route::group([], function () {
    Route::get('/getExchangeBalanceDetail', 'Admin\ExchangeBalanceController@getExchangeBalanceDetail');
    Route::get('/', 'Admin\AdminController@index');
    Route::group(['prefix' => 'admin-group'], function () {
        Route::post('/checkOldPassword', 'Admin\AdminController@checkOldPassword');
        Route::put('/changeAdminPassword', 'Admin\AdminController@changeAdminPassword');
    });
    Route::get('/getAllCoins', 'Admin\ExchangeBalanceController@getAllCoins');

    Route::get('/transactions/usd-withdraw/export', 'Admin\AdminController@exportUsdTransactionsToExcel');

    Route::group(['prefix' => 'api'], function () {

        Route::group(['prefix' => 'vouchers', 'namespace' => 'Admin'], function () {
            Route::get('/', [VoucherController::class, 'index']);
            Route::get('/{id}', [VoucherController::class, 'show']);
            Route::post('/', [VoucherController::class, 'store']);
            Route::put('/{id}', [VoucherController::class, 'update']);
            Route::post('/{id}/delete', [VoucherController::class, 'destroy']);
        });

        Route::post('register-erc20', 'Admin\Erc20Controller@registerErc20');
        Route::get('erc20-contract-information', 'Admin\Erc20Controller@getErc20ContractInformation');
        Route::get('validate-contract-address', 'Admin\CoinController@validateContractAddress');

        Route::group(['prefix' => 'instruments'], function () {
            Route::post('/update', 'Admin\InstrumentController@updateInstruments');
            Route::post('/create', 'Admin\InstrumentController@createInstruments');
            Route::delete('/{id}/delete', 'Admin\InstrumentController@deleteInstruments');
            Route::get('/', 'Admin\InstrumentController@getInstruments');
            Route::get('/settings', 'Admin\InstrumentController@getInstrumentsSettings');
            Route::get('/getDropDownData', 'Admin\InstrumentController@getInstrumentDropdownData');
            Route::get('/get-coin-active', 'Admin\InstrumentController@getCoinActive');
            Route::get('/get-coin-index-active', 'Admin\InstrumentController@getIndexCoinActive');
        });
        Route::post('/bonus-balance', 'API\AirdropAPIController@applyBonusBalance');
        Route::post('/refund-bonus-balance', 'API\AirdropAPIController@refundBonusBalance');
        Route::group(['prefix' => 'instruments'], function () {
            Route::post('/update', 'Admin\InstrumentController@updateInstruments');
            Route::post('/create', 'Admin\InstrumentController@createInstruments');
            Route::delete('/{id}/delete', 'Admin\InstrumentController@deleteInstruments');
            Route::get('/', 'Admin\InstrumentController@getInstruments');
            Route::get('/settings', 'Admin\InstrumentController@getInstrumentsSettings');
            Route::get('/getDropDownData', 'Admin\InstrumentController@getInstrumentDropdownData');
            Route::get('/get-coin-active', 'Admin\InstrumentController@getCoinActive');
            Route::get('/get-coin-index-active', 'Admin\InstrumentController@getIndexCoinActive');
        });
        Route::group(['prefix' => 'airdrop'], function () {
            Route::post('/change-status', 'API\AirdropAPIController@changeStatus');
            Route::post('/change-status-fee-wallet', 'API\AirdropAPIController@changeStatusPayFee');
            Route::post('/change-status-enable-fee-wallet', 'API\AirdropAPIController@changeStatusEnableWallet');
            Route::post('/settings', 'API\AirdropAPIController@updateAirdropSetting');
            Route::get('/settings', 'API\AirdropAPIController@getAirdropSetting');
            Route::get('/render-settings', 'API\AirdropAPIController@getAirdropSettingToRender');
            Route::get('/all-settings', 'API\AirdropAPIController@getAllAirdropSetting');

            Route::get('/user-settings', 'API\AirdropAPIController@getListAirdropUserSetting');
            Route::post('/user-settings', 'API\AirdropAPIController@createAirdropUserSetting');
            Route::put('/user-settings/{user_id}/update', 'API\AirdropAPIController@updateAirdropUserSetting');
            Route::delete('/user-settings/{user_id}/delete', 'API\AirdropAPIController@deleteAirdropUserSetting');

            Route::get('/history', 'API\AirdropAPIController@getListAirdropHistory');
            Route::get('/payment-history', 'API\AirdropAPIController@getAirdropPaymentHistory');
            Route::get('/cashback-history', 'API\AirdropAPIController@getCashbackHistory');
            Route::get('/total-bonus', 'API\AirdropAPIController@getTotalBonusDividend');
            Route::get('/volume-ranking', 'API\AirdropAPIController@getTradingVolumeRanking');

            Route::get('/get-pairs', 'API\AirdropAPIController@getPairs');

            Route::get('/manual-dividend-history', 'API\AirdropAPIController@getManualDividendHistory');
            Route::get('/auto-dividend-history', 'API\AirdropAPIController@getAutoDividendHistory');
        });
        Route::group(['prefix' => 'auto-dividend'], function () {
            Route::get('/setting', 'API\AirdropAPIController@getAutoDividendSetting');
            Route::post('/update-all-status', 'API\AirdropAPIController@enableOrDisableAll');
            Route::post('/update-status', 'API\AirdropAPIController@enableOrDisableSetting');
            Route::post('/update-setting', 'API\AirdropAPIController@updateAutoDividendSetting');
            Route::post('/reset-max-bonus', 'API\AirdropAPIController@resetMaxBonus');
            Route::post('/reset-payout-amount', 'API\AirdropAPIController@resetAutoDividendSetting');
        });

        Route::group(['prefix' => 'circuit-breaker'], function () {
            // Circuit Breaker
            Route::get('/settings', 'Admin\CircuitBreakerController@getSetting');
            Route::post('/settings/update', 'Admin\CircuitBreakerController@updateSetting');
            Route::post('/change-status', 'Admin\CircuitBreakerController@changeStatus');

            // Coin Pair Setting
            Route::get('/coin-pair-setting', 'Admin\CircuitBreakerController@getCoinPairSetting');
            Route::post('/update-coin-pair-setting', 'Admin\CircuitBreakerController@updateCoinPairSetting');
        });

        Route::group(['prefix' => 'administrators'], function () {
            Route::get('/', 'Admin\AdminController@getAdmins');
            Route::get('/{id}', 'Admin\AdminController@getAdministratorById');
            Route::post('/create', 'Admin\AdminController@createNewOrUpdateAdministrator');
            Route::post('/update', 'Admin\AdminController@createNewOrUpdateAdministrator');
            Route::delete('delete', 'Admin\AdminController@deleteAdministrator');
        });

        Route::group(['prefix' => 'networks'], function () {
            Route::get('/', 'Admin\NetworkController@getNetworks');
            Route::get('/{id}', 'Admin\NetworkController@getNetworkById');
            Route::post('/create', 'Admin\NetworkController@createNewOrUpdateNetwork');
            Route::post('/update/{id}', 'Admin\NetworkController@createNewOrUpdateNetwork');
            Route::delete('/delete/{id}', 'Admin\NetworkController@deleteNetwork');
        });

        Route::group(['prefix' => 'network-coins'], function () {
            Route::get('/', 'Admin\NetworkCoinController@getNetworkCoins');
            Route::get('/{id}', 'Admin\NetworkCoinController@getNetworkCoinById');
            Route::post('/create', 'Admin\NetworkCoinController@createNewOrUpdateNetworkCoin');
            Route::post('/update/{id}', 'Admin\NetworkCoinController@createNewOrUpdateNetworkCoin');
            Route::delete('/delete/{id}', 'Admin\NetworkCoinController@deleteNetworkCoin');
        });

        Route::group(['prefix' => 'coins'], function () {
            Route::get('/', 'Admin\CoinController@getCoinsWithPagination');
            Route::get('/{id}', 'Admin\CoinController@getCoinById');
            Route::post('/update/{id}', 'Admin\CoinController@update');
            Route::post('/create', 'Admin\CoinController@store');
        });
        Route::group(['prefix' => 'spot'], function () {
            Route::get('/markets', 'Admin\MarketController@getMarkets');
			Route::post('/markets', 'Admin\MarketController@createPair');
			Route::get('/market/{id}', 'Admin\MarketController@getMarket');
			Route::put('/market/{id}', 'Admin\MarketController@updatePair');
        });

        Route::group(['prefix' => 'referral'], function () {
            Route::post('/change-status', 'API\ReferralAPIController@changeStatus');
            Route::get('/settings', 'API\ReferralAPIController@getReferralSettings');
            Route::post('/settings', 'API\ReferralAPIController@updateReferralSettings');
            Route::get('/history', 'API\ReferralAPIController@getReferralHistory');
            Route::get('/invitees', 'Admin\ReferralController@getReferralsList');
            Route::get('/invitees/export', 'Admin\ReferralController@exportReferralsList');
            Route::get('/referrers', 'Admin\ReferralController@getReferrersList');
            Route::get('/referrers/export', 'Admin\ReferralController@exportReferrersList');
            Route::get('/referrers/{id}', 'Admin\ReferralController@getReferrerDetails');
            Route::get('/referrers/{id}/transactions', 'Admin\ReferralController@getReferrerTransactions');
            Route::get('/referrers/{id}/transactions/export', 'Admin\ReferralController@exportReferrerTransactions');
            Route::get('/commission-statistics', 'Admin\ReferralController@getCommissionStatistics');
            Route::get('/commission-statistics/export', 'Admin\ReferralController@exportCommissionStatistics');

            Route::group(['prefix' => 'dashboard'], function () {
                Route::get('/trade-volume', 'Admin\ReferralController@getTradeVolumeStatistics');
                Route::get('/referrers-summary', 'Admin\ReferralController@getReferrersSummary');
                Route::get('/referrals-summary', 'Admin\ReferralController@getReferralsSummary');
                Route::get('/distributed-commission/overview', 'Admin\ReferralController@getDistributedCommissionOverview');
                Route::get('/distributed-commission/overview/export', 'Admin\ReferralController@distributedCommissionOverviewExport');
                Route::get('/distributed-commission/statistics', 'Admin\ReferralController@getDistributedCommissionStatistics');
                Route::get('chart/funnel', [ReferralController::class, 'overallReferralConversionRate'] );
                Route::get('top/performers', [ReferralController::class, 'topPerformers'] );
            });
        });


        Route::group(['prefix' => 'referrer'], function () {
            Route::get('level', [ReferralController::class, 'referrerClientLevels'] );
            Route::get('level/{level}', [ReferralController::class, 'referrerClientLevel'] );
            Route::put('level/{level}', [ReferralController::class, 'setReferrerClientLevel'] );
        });

        Route::get('/get-infomation-coins', 'Admin\AdminController@getInfomationCoins');
        Route::get('/user-login-history', 'Admin\AdminController@getUserLoginHistory');

        Route::get('/email-marketing', 'Admin\AdminController@getEmailMarketing');

        Route::get('/email-marketing/edit', 'Admin\AdminController@editEmailMarketing');

        Route::post('/email-marketing/update', 'Admin\AdminController@updateEmailMarketing');

        Route::post('/email-marketing/create', 'Admin\AdminController@createEmailMarketing');

        Route::post('/email-marketing/send', 'Admin\AdminController@sendEmailsMarketing');

        Route::delete('/email-marketing/delete/{id}', 'Admin\AdminController@deleteEmailMarketing');

        Route::get('/email-marketing/sent-emails/{id}', 'Admin\AdminController@getEmailMarketingSendedHistories');

        Route::post('/tool/update-data', 'ToolController@updateData');

        Route::post('/clear-cache', 'ToolController@clearCache');

        Route::get('/users', 'API\UserAPIController@users');

        Route::get('/total-user', 'API\UserAPIController@getTotalUser');

        Route::get('/referrers', 'API\UserAPIController@referrers');

        Route::get('/transaction/fee', 'API\TransactionAPIController@getFee');

        Route::get('/order/fee', 'API\OrderAPIController@getFee');

        Route::get('/transaction/fee-total', 'API\TransactionAPIController@getTotalFee');

        Route::get('/order/fee-total', 'API\OrderAPIController@getTotalFee');

        Route::get('/user/referrer-fee', 'API\UserAPIController@getReferrerFee');

        Route::get('/usd-transactions', 'Admin\AdminController@getUsdTransactions');

        Route::put('/confirm-usd-transaction', 'Admin\AdminController@confirmUsdTransaction');

        Route::put('/reject-usd-transaction', 'Admin\AdminController@rejectUsdTransaction');

        Route::put('/send-transaction', 'Admin\AdminController@sendTransaction');

        Route::put('/cancel-transaction', 'Admin\AdminController@cancelTransaction');

        Route::get('/transaction-units', 'Admin\SettingController@getTransactionUnits');

        Route::put('/transaction-units', 'Admin\SettingController@updateTransactionUnits');

        Route::get('/transaction-fees', 'Admin\SettingController@getTransactionFees');

        Route::put('/transaction-fees', 'Admin\SettingController@updateTransactionFees');

        Route::get('/withdrawal-fees', 'Admin\SettingController@getWithdrawalFees');

        Route::put('/withdrawal-fees', 'Admin\SettingController@updateWithdrawalFees');

        Route::post('/price-aml', 'Admin\SettingController@updatePriceAML');

        Route::get('/bank-accounts', 'Admin\AdminController@getBankAccounts');

        Route::post('/bank-account', 'Admin\AdminController@createBankAccount');

        Route::put('/bank-account', 'Admin\AdminController@updateBankAccount');

        Route::delete('/bank-account', 'Admin\AdminController@deleteBankAccount');

        Route::get('/customer-usd-total', 'Admin\AdminController@getCustomerUsdTotal');

        Route::get('/wallets/{currency}', 'Admin\WalletController@getWalletInfo');

        Route::post('/wallets', 'Admin\WalletController@create');

        Route::delete('/wallets/{id}', 'Admin\WalletController@removeWallet');

        Route::get('/total-balance', 'Admin\WalletController@getTotalBalances');

        Route::get('/wallet-balance', 'Admin\WalletController@getWalletBalances');

        Route::get('/users2', 'Admin\AdminController@getUsers');

        Route::post('/save-show-remain-aml', 'API\SettingAPIController@update');

        Route::get('/transactions', 'Admin\AdminController@getTransactions');

        Route::get('/user_balances', 'Admin\AdminController@getUserBalances');

        Route::group(['prefix' => 'user-kyc'], function () {
            Route::get('/', 'Admin\AdminController@getUserKycs');
            Route::get('/detail', 'Admin\AdminController@getDetailUserKyc');
            Route::put('/verify', 'Admin\AdminController@verifyUserKyc');
            Route::put('/reject', 'Admin\AdminController@rejectUserKyc');

            Route::group(['prefix' => 'sumsub'], function () {
                Route::get('/', 'Admin\AdminController@getSumsubUserKycs');
                Route::get('/detail', 'Admin\AdminController@getSumsubDetailUserKyc');
                Route::put('/verify', 'Admin\AdminController@verifySumsubUserKyc');
                Route::put('/reject', 'Admin\AdminController@rejectSumsubUserKyc');
            });
        });

        Route::group(['prefix' => 'admin-notice'], function () {
            Route::get('/', 'Admin\AdminController@getNotices');
            Route::get('/edit', 'Admin\AdminController@getEditNotice');
            Route::post('/update', 'Admin\AdminController@updateNotice');
            Route::post('/create', 'Admin\AdminController@createNotice');
            Route::delete('/delete/{id}', 'Admin\AdminController@deleteNotice');
        });

        Route::get('/user_access_histories', 'Admin\AdminController@getUserAccessHistories');
        Route::get('/amal-net', 'Admin\AdminController@getAMALNet');
        Route::get('/profit', 'Admin\AdminController@getProfit');

        Route::get('/orders', 'API\OrderAPIController@getTransactionHistoryForUser');

        Route::group(['prefix' => 'notices'], function () {
            Route::get('/', 'API\ServiceCenterAPIController@getNotices');
            Route::post('/', 'API\ServiceCenterAPIController@createNotice');
            Route::get('/{id}', 'API\ServiceCenterAPIController@getNotice');
            Route::put('/{id}', 'API\ServiceCenterAPIController@updateNotice');
            Route::delete('/{id}', 'API\ServiceCenterAPIController@removeNotice');
        });

        Route::get('/withdrawal-limits', 'Admin\AdminController@getWithdrawalLimits');

        Route::put('/withdrawal-limit', 'Admin\AdminController@updateWithdrawalLimit');

        Route::get('/currency-step-unit-usd', 'Admin\AdminController@getCoinStepUnitByUsd');

        Route::put('/currency-step-unit-usd', 'Admin\AdminController@updateCoinStepUnitByUsd');

        Route::group(['prefix' => 'notifications'], function () {
            Route::get('/', 'Admin\AdminController@getNotifications');
            Route::get('read/{notificationId}', 'Admin\AdminController@markAsRead');
        });

        Route::get('/user', 'Admin\AdminController@getCurrentAdmin');

        Route::get('/deposit-badge', 'Admin\AdminController@getDepositPageBadge');

        Route::get('/withdraw-badge', 'Admin\AdminController@getWithdrawPageBadge');

        Route::group(['prefix' => 'orders'], function () {
            Route::get('/history', 'Admin\AdminController@getTransactionHistory');

            Route::get('/trading-histories', 'Admin\AdminController@getTradingHistories');

            Route::get('/trading-histories/{orderId}', 'Admin\AdminController@getTradingsByOrder');

            Route::get('/pending', 'Admin\AdminController@getOrderPending');
        });

        Route::group(['prefix' => 'users'], function () {
            Route::put('{id}', 'API\UserAPIController@update');
            Route::put('otp/{id}', 'API\UserAPIController@updateSettingOtp');
        });

        Route::group(['prefix' => 'coins-confirmations', 'namespace' => 'Admin'], function () {
            Route::get('/', 'CoinsConfirmationController@index');
            Route::put('update/{id}', 'CoinsConfirmationController@update');
            Route::put('update-all', 'CoinsConfirmationController@updateAll');
        });

        Route::group(['prefix' => 'fee-levels', 'namespace' => 'Admin'], function () {
            Route::get('/', 'FeeLevelController@index');
            Route::put('/{id}', 'FeeLevelController@update');
        });

        Route::group(['prefix' => 'market-fee-setting', 'namespace' => 'Admin'], function () {
            Route::get('/', 'MarketFeeSettingController@index');
            Route::put('/', 'MarketFeeSettingController@update');
        });

        Route::group(['prefix' => 'enable-fee-setting', 'namespace' => 'Admin'], function () {
            Route::get('/', 'EnableFeeSettingController@index');
            Route::get('/user-settings', 'EnableFeeSettingController@getUserListSetting');
            Route::put('/update-user-settings', 'EnableFeeSettingController@updateUserSetting');
            Route::post('/add-user-settings', 'EnableFeeSettingController@addUserSetting');
            Route::post('/user-settings/{id}/delete', 'EnableFeeSettingController@deleteUserSetting');
        });

        Route::group(['prefix' => 'enable-withdrawal-setting', 'namespace' => 'Admin'], function () {
            Route::get('/', 'EnableWithdrawalSettingController@index');
            Route::get('/user-settings', 'EnableWithdrawalSettingController@getUserListSetting');
            Route::put('/update-user-settings', 'EnableWithdrawalSettingController@updateUserSetting');
            Route::post('/add-user-settings', 'EnableWithdrawalSettingController@addUserSetting');
            Route::post('/user-settings/{id}/delete', 'EnableWithdrawalSettingController@deleteUserSetting');
        });

        Route::group(['prefix' => 'enable-trading-setting', 'namespace' => 'Admin'], function () {
            Route::get('/', 'EnableTradingSettingController@index');
            Route::get('/user-settings', 'EnableTradingSettingController@getUserListSetting');
            Route::put('/update-user-settings', 'EnableTradingSettingController@updateUserSetting');
            Route::put('/update-coin-settings', 'EnableTradingSettingController@updateCoinSetting');
            Route::post('/add-user-settings', 'EnableTradingSettingController@addUserSetting');
            Route::post('/user-settings/{id}/delete', 'EnableTradingSettingController@deleteUserSetting');
        });

        Route::group(['prefix' => 'cold-wallet-setting', 'namespace' => 'Admin'], function () {
            Route::get('/', 'ColdWalletSettingController@index');
            Route::get('/validate-address', 'ColdWalletSettingController@validateAddressFromExternal');
            Route::put('/', 'ColdWalletSettingController@update');
            Route::get('/validate-address-from-internal', 'ColdWalletSettingController@validateAddress');
            Route::get('/common-validate-address', 'ColdWalletSettingController@commonValidateAddress');
            Route::post('/send-mail-to-update-cold-wallet', 'ColdWalletSettingController@sendEmailUpdateColdWallet');
        });

        Route::group(['prefix' => 'withdrawal-limit-levels', 'namespace' => 'Admin'], function () {
            Route::get('/', 'WithdrawalLimitController@index');
            Route::put('update/{id}', 'WithdrawalLimitController@update');
        });

        Route::group(['prefix' => 'site-settings', 'namespace' => 'Admin'], function () {
            Route::get('/', 'SiteSettingController@getSettingSite');
            Route::post('update', 'SiteSettingController@updateSettingSite');
        });

        Route::group(['prefix' => 'socical-networks', 'namespace' => 'Admin'], function () {
            Route::get('/', 'SiteSettingController@getSocialNetworks');
            Route::post('/', 'SiteSettingController@addSocialNetwork');
            Route::post('/update', 'SiteSettingController@updateSocialNetWork');
            Route::delete('/{id}', 'SiteSettingController@removeSocialNetwork');
        });
        Route::get('user/{userId}/devices', 'Admin\UserDeviceController@getDeviceRegister');
        Route::delete('user/{userId}/device/{id}', 'Admin\UserDeviceController@deleteDevice');

        Route::get('/countries', 'Admin\AdminController@getCountries');

        Route::group(['prefix' => 'salespoint'], function () {
            Route::get('/buy-history', 'Admin\AdminController@getBuyHistory');
            Route::get('/cash-back-history', 'Admin\AdminController@getCashBackHistory');
        });

        Route::group(['prefix' => 'leaderboard'], function () {
            Route::get('/get-leaderboard-setting', 'Admin\LeaderboardController@getLeaderboardSetting');
            Route::put('/update-leaderboard-setting', 'Admin\LeaderboardController@updateLeaderboardSetting');
            Route::post('/change-trading-setting', 'Admin\LeaderboardController@changeSetting');
            Route::get('/get-self-trading-setting', 'Admin\LeaderboardController@getSettingSelfTrading');
            Route::get('/get-top-trading-volume-ranking', 'Admin\LeaderboardController@getTopTradingVolumeRanking');
        });

        Route::group(['prefix' => 'promotions'], function () {
            Route::get('/', 'Admin\PromotionController@getPromotions');
            Route::get('/{promotion}', 'Admin\PromotionController@getPromotionDetails');
            Route::post('/', 'Admin\PromotionController@createPromotion');
            Route::post('/{promotion}', 'Admin\PromotionController@updatePromotion');
            Route::patch('/{promotion}', 'Admin\PromotionController@pinPromotion');
            Route::delete('/{promotion}', 'Admin\PromotionController@deletePromotion');
        });

        Route::group(['prefix' => 'promotion-categories'], function () {
            Route::get('/', 'Admin\PromotionCategoryController@index');
            Route::post('/', 'Admin\PromotionCategoryController@store');
            Route::get('/{category}', 'Admin\PromotionCategoryController@show');
            Route::put('/{category}', 'Admin\PromotionCategoryController@update');
            Route::delete('/{category}', 'Admin\PromotionCategoryController@destroy');
        });

        Route::resource('/aml-settings', 'Admin\AmlSettingController')->only([
            'index', 'update'
        ]);

        Route::get('/price-group-currency', 'Admin\AdminController@getPriceGroupCurrency');
        Route::get('/get-all-coin', 'Admin\AdminController@getAllCoin');

        Route::group(['prefix' => 'user-group-setting', 'namespace' => 'Admin'], function () {
            Route::get('/', 'UserGroupSettingController@getList');
            Route::post('/', 'UserGroupSettingController@addNew');
            Route::post('/update', 'UserGroupSettingController@update');
            Route::delete('/{id}', 'UserGroupSettingController@remove');
        });
        Route::group(['prefix' => 'user-group', 'namespace' => 'Admin'], function () {
            Route::get('/', 'UserGroupController@getList');
            Route::post('/', 'UserGroupController@update');
            Route::post('/delete', 'UserGroupController@delete');
        });
        Route::group(['prefix' => 'users/statistics/overview'], function () {
            Route::group(['prefix' => 'deposit'], function () {
                Route::get('no', [UsersStatisticsOverviewController::class, 'noDeposit']);
                Route::get('top/{currency}',[UsersStatisticsOverviewController::class,'topDeposit']);
            });
            Route::group(['prefix' => 'withdraw'], function () {
                Route::get('pending', [UsersStatisticsOverviewController::class, 'pendingWithdraw']);
                Route::get('top/{currency}',[UsersStatisticsOverviewController::class,'topWithdraw']);
            });
            Route::group(['prefix' => 'list'], function () {
                Route::get('gains', [UsersStatisticsOverviewController::class, 'listGains']);
                Route::get('losers',[UsersStatisticsOverviewController::class,'listLosers']);
            });
        }); 
        Route::group(['prefix' => 'player/report'], function() {
              Route::group(['prefix' => 'real'], function() {
                Route::get('balance', [PlayerReportRealController::class, 'balance']);
                Route::get('balance/export', [PlayerReportRealController::class, 'exportBalance']);
            });
        });
    });

    Route::group(['prefix' => 'market'], function () {
        Route::get('hot-symbols', 'Admin\MarketController@getHotSymbols');
        Route::put('hot-symbols', 'Admin\MarketController@updateHotSymbols');
    });

    Route::get('/order/transactions/export', 'OrderController@exportToExcelForUser');

    Route::get('/{view?}', 'Admin\AdminController@index')->where('view', '(.*)');
    Route::group(['prefix' => 'hotwallet'], function () {
        Route::post('create-eos-receive-address', 'API\HotWalletAPIController@createEOSReceiveAddress');
        Route::post('create-usdt-receive-address', 'API\HotWalletAPIController@createUSDTReceiveAddress');
    });

});
