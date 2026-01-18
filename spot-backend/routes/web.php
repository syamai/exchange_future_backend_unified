<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelescopeController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::group(['prefix' => 'test'], function () {
//     Route::get('/form', 'Test\RateLimitController@form');
//     Route::post('/run-rate-limit', 'Test\RateLimitController@runRateLimit');
// });


// Route::get('/', function() {
//     return redirect('admin');
// });

// // Auth::routes();

// Route::post('/confirm-register', 'Auth\RegisterController@confirmRegister')->name('confirmRegister');
// Route::get('/resend-register-email', 'Auth\RegisterController@resendConfirmEmail')->name('resendConfirmEmail');
// Route::get('/confirm-email', 'Auth\RegisterController@confirmEmail')->name('confirmEmail');

// Route::put('/locale', 'API\UserAPIController@setLocale');

// Route::group(['middleware' => 'auth:web'], function () {
//     Route::get('/profit-and-loss/transactions/export' , 'ProfitAndLossController@exportToExcel');
//     Route::get('order/pending/export', 'OrderController@downloadOrderPending');
//     Route::get('/support_login', 'SupportController@getSupport');
// });
// Route::get('404', 'HomeController@showNotFoundPage');
// Route::get('auth', 'HomeController@authUrl')->middleware('auth');;
// Route::get('/white-paper/{lang?}', 'LandingController@getWhitePaper');

// Route::get('/webview/{view?}', 'HomeController@getWebview')->where('view', '(.*)');
// Route::get('/{view?}', 'HomeController@index')->where('view', '(.*)');


Route::get('login-telescope', [TelescopeController::class, 'loginView'])->name('login-telescope');
Route::post('login-telescope', [TelescopeController::class, 'login'])->name('login-telescope.post');
