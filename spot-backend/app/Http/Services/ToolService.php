<?php


namespace App\Http\Services;

use Illuminate\Support\Facades\DB;

/**
 * Class ToolService
 * @package App\Http\Services
 */
class ToolService
{
    /**
     * Update Accounts
     * @param $request
     * @return null
     */
    public function updateAccounts($request)
    {
        $coin1 = $request['coin1'];
        $coin2 = $request['coin2'];
        switch ($coin1) {
            case 'usd':
                DB::table('usd_accounts')
                    ->join('users', 'users.id', '=', 'usd_accounts.id')
                    ->where('users.email', '=', $request['email'])
                    ->update([
                        'usd_accounts.balance' => $request['balanceCoin1'],
                        'usd_accounts.available_balance' => $request['availableCoin1']
                    ]);
                break;
            case 'btc':
                DB::table('btc_accounts')
                    ->join('users', 'users.id', '=', 'btc_accounts.id')
                    ->where('users.email', '=', $request['email'])
                    ->update([
                        'btc_accounts.balance' => $request['balanceCoin1'],
                        'btc_accounts.available_balance' => $request['availableCoin1']
                    ]);
                break;
            case 'bch':
                DB::table('bch_accounts')
                    ->join('users', 'users.id', '=', 'bch_accounts.id')
                    ->where('users.email', '=', $request['email'])
                    ->update([
                        'bch_accounts.balance' => $request['balanceCoin1'],
                        'bch_accounts.available_balance' => $request['availableCoin1']
                    ]);
                break;
            case 'eth':
                DB::table('eth_accounts')
                    ->join('users', 'users.id', '=', 'eth_accounts.id')
                    ->where('users.email', '=', $request['email'])
                    ->update([
                        'eth_accounts.balance' => $request['balanceCoin1'],
                        'eth_accounts.available_balance' => $request['availableCoin1']
                    ]);
                break;
            case 'etc':
                DB::table('etc_accounts')
                    ->join('users', 'users.id', '=', 'etc_accounts.id')
                    ->where('users.email', '=', $request['email'])
                    ->update([
                        'etc_accounts.balance' => $request['balanceCoin1'],
                        'etc_accounts.available_balance' => $request['availableCoin1']
                    ]);
                break;
            case 'xrp':
                DB::table('xrp_accounts')
                    ->join('users', 'users.id', '=', 'xrp_accounts.id')
                    ->where('users.email', '=', $request['email'])
                    ->update([
                        'xrp_accounts.balance' => $request['balanceCoin1'],
                        'xrp_accounts.available_balance' => $request['availableCoin1']
                    ]);
                break;
            case 'ltc':
                DB::table('ltc_accounts')
                    ->join('users', 'users.id', '=', 'ltc_accounts.id')
                    ->where('users.email', '=', $request['email'])
                    ->update([
                        'ltc_accounts.balance' => $request['balanceCoin1'],
                        'ltc_accounts.available_balance' => $request['availableCoin1']
                    ]);
                break;
            case 'dash':
                DB::table('dash_accounts')
                    ->join('users', 'users.id', '=', 'dash_accounts.id')
                    ->where('users.email', '=', $request['email'])
                    ->update([
                        'dash_accounts.balance' => $request['balanceCoin1'],
                        'dash_accounts.available_balance' => $request['availableCoin1']
                    ]);
                break;

            default:
                # code...
                break;
        }
        switch ($coin2) {
            case 'usd':
                DB::table('usd_accounts')
                    ->join('users', 'users.id', '=', 'usd_accounts.id')
                    ->where('users.email', '=', $request['email'])
                    ->update([
                        'usd_accounts.balance' => $request['balanceCoin2'],
                        'usd_accounts.available_balance' => $request['availableCoin2']
                    ]);
                break;
            case 'btc':
                DB::table('btc_accounts')
                    ->join('users', 'users.id', '=', 'btc_accounts.id')
                    ->where('users.email', '=', $request['email'])
                    ->update([
                        'btc_accounts.balance' => $request['balanceCoin2'],
                        'btc_accounts.available_balance' => $request['availableCoin2']
                    ]);
                break;
            case 'bch':
                DB::table('bch_accounts')
                    ->join('users', 'users.id', '=', 'bch_accounts.id')
                    ->where('users.email', '=', $request['email'])
                    ->update([
                        'bch_accounts.balance' => $request['balanceCoin2'],
                        'bch_accounts.available_balance' => $request['availableCoin2']
                    ]);
                break;
            case 'eth':
                DB::table('eth_accounts')
                    ->join('users', 'users.id', '=', 'eth_accounts.id')
                    ->where('users.email', '=', $request['email'])
                    ->update([
                        'eth_accounts.balance' => $request['balanceCoin2'],
                        'eth_accounts.available_balance' => $request['availableCoin2']
                    ]);
                break;
            case 'etc':
                DB::table('etc_accounts')
                    ->join('users', 'users.id', '=', 'etc_accounts.id')
                    ->where('users.email', '=', $request['email'])
                    ->update([
                        'etc_accounts.balance' => $request['balanceCoin2'],
                        'etc_accounts.available_balance' => $request['availableCoin2']
                    ]);
                break;
            case 'xrp':
                DB::table('xrp_accounts')
                    ->join('users', 'users.id', '=', 'xrp_accounts.id')
                    ->where('users.email', '=', $request['email'])
                    ->update([
                        'xrp_accounts.balance' => $request['balanceCoin2'],
                        'xrp_accounts.available_balance' => $request['availableCoin2']
                    ]);
                break;
            case 'ltc':
                DB::table('ltc_accounts')
                    ->join('users', 'users.id', '=', 'ltc_accounts.id')
                    ->where('users.email', '=', $request['email'])
                    ->update([
                        'ltc_accounts.balance' => $request['balanceCoin2'],
                        'ltc_accounts.available_balance' => $request['availableCoin2']
                    ]);
                break;
            case 'dash':
                DB::table('dash_accounts')
                    ->join('users', 'users.id', '=', 'dash_accounts.id')
                    ->where('users.email', '=', $request['email'])
                    ->update([
                        'dash_accounts.balance' => $request['balanceCoin2'],
                        'dash_accounts.available_balance' => $request['availableCoin2']
                    ]);
                break;

            default:
                # code...
                break;
        }
        return null;
    }
}
