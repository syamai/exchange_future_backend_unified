<?php
/**
 * Created by PhpStorm.
 * Date: 5/2/19
 * Time: 3:40 PM
 */

Route::namespace('Transaction\Http\Controllers\API')
    ->prefix('api/v1')
    ->middleware(['api'])
    ->group(function () {

        Route::group(['middleware' => 'auth:api'], function () {
            Route::post('/withdraw', 'TransactionAPIController@withdraw')->name('withdraw');
//            Route::get('/withdraws', 'TransactionAPIController@getWithdraws');
//            Route::get('/change-status', 'TransactionAPIController@setTransactionStatus');
            Route::get('/deposit-history', 'TransactionAPIController@getDepositHistory');
//            Route::get('/get-show-remain-aml', 'SettingAPIController@index');
            Route::get('/balance-transaction-main/{currency}', 'UserBalanceAPIController@getBalanceTransactionMain');
            Route::get('/get-decimal/{currency}', 'UserBalanceAPIController@getDecimalCoin');
        });

        Route::get('/verify-withdraw/{transactionId}', 'WithdrawVerifyAPIController@verifyWithdraw')->name('verify.withdraw');
    });
