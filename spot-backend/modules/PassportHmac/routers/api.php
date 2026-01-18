<?php

    // Route::group(['prefix' => 'api-creating'], function () {
    //     Route::get('get', 'PassportHmac\Http\Controllers\HmacTokenController@index');
    //     Route::put('update', 'PassportHmac\Http\Controllers\HmacTokenController@store');
    //     Route::post('modify', 'PassportHmac\Http\Controllers\HmacTokenController@store');
    //     Route::delete('remove', 'PassportHmac\Http\Controllers\HmacTokenController@destroy');
    // });

// Route::group(['middleware' => 'auth:api', 'prefix' => 'api/v1/'], function () {
//     Route::post('create-pnl-token', 'PassportHmac\Http\Controllers\HmacTokenController@createTokenPnlChart');
    // Route::get('balance/{currency}', 'API\UserAPIController@getDetailsUserSpotBalance');
// });

Route::namespace('PassportHmac\Http\Controllers')
    ->prefix('api/v1/')
    ->middleware(['api', 'auth:api'])
    ->group(function () {
        Route::resource('hmac-tokens', 'HmacTokenController')->only(['index', 'create', 'update', 'store', 'destroy']);
        Route::post('create-pnl-token', 'HmacTokenController@createTokenPnlChart');
        Route::get('list-scopes', 'HmacTokenController@listScopes');
    });
