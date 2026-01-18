<?php

use App\Http\Controllers\API\SettingAPIController;
use App\Http\Controllers\Partner\AuthController;
use App\Http\Controllers\Partner\DashboardController;
use App\Http\Controllers\Partner\PartnerController;
use App\Http\Controllers\Partner\ReferralController;
use App\Http\Controllers\Partner\TradeController;
use App\Http\Controllers\Partner\UserController;
use App\Http\Middleware\AuthenticateUser;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api'], function () {

    Route::get('get-client-secret', [SettingAPIController::class, 'getClients']);

    Route::post('/login', [
        AuthController::class, 'login'
    ])->middleware(IPActive\Define::LOGIN_MIDDLEWARE, 'encrypt_pass', 'pre_login', AuthenticateUser::class, 'suf_login', 'partner_login');

    Route::group(['middleware' => ['auth:api', 'is_partner']], function () {
        Route::get('profile', [AuthController::class, 'getProfile']);
        Route::group(['prefix' => 'dashboard'], function () {
            Route::get('balance', [DashboardController::class, 'getTodayCommission']);
            Route::get('rate-commission', [DashboardController::class, 'getRateCommission']);
            Route::get('rate-commission/history', [DashboardController::class, 'getRateCommissionHistory']);
            Route::get('balance-issuance/history', [DashboardController::class, 'getBalanceIssuanceHistory']);
            Route::group(['prefix' => 'trade'], function () {
                Route::get('today', [DashboardController::class, 'getTodayTrade']);
                Route::get('history', [DashboardController::class, 'getTradeHistory']);
            });
        });
        Route::group(['prefix' => 'data-panel'], function () {
            Route::get('ref-statistic', [ReferralController::class, 'getRefStatistic']);
            
            Route::group(['prefix' => 'trade-commission'], function () {
                Route::get('overview', [ReferralController::class, 'getTradeCommissionOverview']);
                Route::get('volume', [ReferralController::class, 'getTradeCommissionVolume']);
                Route::get('traders', [ReferralController::class, 'getTradeCommissionTraders']);
                Route::get('commission', [ReferralController::class, 'getTradeCommissionByCommission']);
            });
            Route::group(['prefix' => 'trade-volume'], function () {
                Route::get('direct', [ReferralController::class, 'getTradeVolumeDirect']);
                Route::get('indirect', [ReferralController::class, 'getTradeVolumeInDirect']);
            });         
        });

        Route::group(['prefix' => 'commission-panel'], function () {
            Route::group(['prefix' => 'user-query'], function () {
                Route::get('/', [ReferralController::class, 'getUserQuery']);
                Route::get('/detail', [ReferralController::class, 'getUserQueryDetail']);
                Route::get('/top10', [ReferralController::class, 'getUserQueryTop10']);
            });
        });

        Route::get('user-management/ref-user', [UserController::class, 'getUserQuery']);
        
        Route::group(['prefix' => 'partner-management'], function () {
            Route::get('estimate-setting-commission/{id}', [PartnerController::class, 'getEstimateSettingCommission']);
            Route::post('setting-commission', [PartnerController::class, 'setCommission']);
            Route::get('partner-list', [UserController::class, 'getPartnerList']);
            Route::get('partner-data', [PartnerController::class, 'getPartnerData']);
        });

        Route::group(['prefix' => 'trade-management'], function () {
            Route::get('spot-query', [TradeController::class, 'getSpotQuery']);
            Route::get('futures-query', [TradeController::class, 'getFuturesQuery']);
        });
        
    });
    
});
