<?php

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::group(['prefix' => 'api'], function () {

        Route::group(['prefix' => 'dashboard'], function () {
            Route::get('partner', 'PartnerAdmin\DashboardController@getPartnerStatistics');
            Route::get('commission', 'PartnerAdmin\DashboardController@getCommissionStatistics');
            Route::group(['prefix' => 'top-list'], function () {
                Route::get('balances', 'PartnerAdmin\DashboardController@getTopListBalances');
                Route::get('referrer', 'PartnerAdmin\DashboardController@getTopListReferrer');
                Route::get('volume-commission', 'PartnerAdmin\DashboardController@getTopListVolumeCommission');
            });
            Route::get('activity-history', 'PartnerAdmin\DashboardController@getActivityHistory');
            Route::group(['prefix' => 'chart'], function () {
                Route::get('new-parter', 'PartnerAdmin\DashboardController@getNewPartnerChart');
                Route::get('fee-commission', 'PartnerAdmin\DashboardController@getFeeCommissionChart');
                Route::get('profit', 'PartnerAdmin\DashboardController@getProfitChart');
            });
        });

        Route::group(['prefix' => 'account'], function () {
            Route::get('/profile', 'PartnerAdmin\AccountController@getProfile');
        });

        Route::group(['prefix' => 'partner'], function () {
            Route::get('/affiliate-profile/{uid}', 'PartnerAdmin\PartnerController@getAffiliateProfile');
            Route::post('/create', 'PartnerAdmin\PartnerController@create');
            Route::get('/list', 'PartnerAdmin\PartnerController@list');
            Route::post('/update/{id}', 'PartnerAdmin\PartnerController@update');
        });

        Route::group(['prefix' => 'commission-request'], function () {
            Route::get('/list', 'PartnerAdmin\PartnerController@listCommissionRequest');
            Route::post('/update/{id}', 'PartnerAdmin\PartnerController@updateCommissionRequest');
        });
        
        Route::group(['prefix' => 'balance'], function () {
            Route::get('/list', 'PartnerAdmin\PartnerController@listBalance');
        });
        
        Route::group(['prefix' => 'referral'], function () {
            Route::get('/distributions', 'PartnerAdmin\ReferralController@getDistributions');
        });
        
        Route::group(['prefix' => 'profit'], function () {
            Route::get('/daily', 'PartnerAdmin\ProfitController@getStatisticsByDate');
        });

        Route::group(['prefix' => 'liquidation'], function () {
            #Route::post('/update-rate/{id}', 'PartnerAdmin\LiquidationCommissionController@updateRateUser');
            Route::get('/unprocessed', 'PartnerAdmin\LiquidationCommissionController@getUnprocessed');
            Route::get('/history', 'PartnerAdmin\LiquidationCommissionController@getHistory');
            Route::get('/detail/{id}', 'PartnerAdmin\LiquidationCommissionController@getDetail');
            Route::put('/unprocessed/rate/{id}', 'PartnerAdmin\LiquidationCommissionController@updateRateUnprocessed');
            Route::post('/unprocessed/send', 'PartnerAdmin\LiquidationCommissionController@sendUnprocessed');
            Route::get('/export/{id}', 'PartnerAdmin\LiquidationCommissionController@exportDetail');
        });
    });
});
