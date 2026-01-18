<?php
/**
 * Created by PhpStorm.
 * Date: 5/25/19
 * Time: 10:08 AM
 */

Route::namespace('Transaction\Http\Controllers\Admin')
    ->prefix('admin/api')
    ->middleware(['admin', 'auth.admin'])
    ->group(function () {
        Route::group(['prefix' => 'transactions'], function () {
            Route::get('/external-withdraws', 'TransactionAdminController@getExternalWithdraws');
            Route::get('/change-status', 'TransactionAdminController@setTransactionStatus');
            Route::post('/registration-remittance', 'TransactionAdminController@registrationRemittance');
            Route::get('/withdrawal-history', 'TransactionAdminController@getWithdrawalHistory');
            Route::get('/{transactionId}', 'TransactionAdminController@getTransaction');
        });
    });
