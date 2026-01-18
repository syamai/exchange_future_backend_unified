<?php
/**
 * Created by PhpStorm.
 * Date: 7/29/19
 * Time: 11:37 AM
 */

Route::namespace('Snapshot\Http\Controllers\Admin')
    ->prefix('admin/api')
//  ->middleware(['admin', 'admin:api'])
    ->group(function () {
        Route::resource('take-profits', 'TakeProfitController');
    });
