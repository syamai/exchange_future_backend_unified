<?php
/**
 * Created by PhpStorm.
 * Date: 5/2/19
 * Time: 3:58 PM
 */

namespace Transaction\Http\Services;

use App\Http\Services\MasterdataService;
use Illuminate\Support\Facades\DB;

class WalletService
{
    private function getTableTypeDepositWithdraw($currency)
    {
        $isSpotMainBalance = env('DEPOSIT_WITHDRAW_SPOT_BALANCE', false);
        if ($isSpotMainBalance) {
            return 'spot_' . $currency . '_accounts';
        }

        return $currency . '_accounts';
    }

    public function updateUserBalanceRaw($currency, $userId, $balance, $availableBalance)
    {
        DB::table($this->getTableTypeDepositWithdraw($currency))
            ->where('id', $userId)
            ->update([
                'balance' => DB::raw('balance + ' . $balance),
                'available_balance' => DB::raw('available_balance + ' . $availableBalance),
            ]);
    }

    public function getUserWalletSummary()
    {
        $summary = [];
        $coins = MasterdataService::getCoins();

        foreach ($coins as $coin) {
            $summary[$coin] = [
                'balance' => DB::table($this->getTableTypeDepositWithdraw($coin))->sum('balance'),
                'currency' => $coin
            ];
        }

        return $summary;
    }

    /**
     * @param $currency
     * @param $userId
     * @param bool $isLockForUpdate
     * @return mixed
     */
    public function getUserBalance($currency, $userId, bool $isLockForUpdate)
    {
        return DB::table($this->getTableTypeDepositWithdraw($currency))
            ->where('id', $userId)
            ->when($isLockForUpdate, function ($query) {
                $query->lockForUpdate();
            })
            ->first();
    }

    public function getUserIdByAddress($currency, $address, $networkId)
    {
        return DB::table("user_blockchain_addresses")
            ->where('currency', $currency)
            ->where('network_id', $networkId)
            ->where('blockchain_address', $address)
            ->value('user_id');
    }
}
