<?php
/**
 * Created by PhpStorm.
 * Date: 5/3/19
 * Time: 5:34 PM
 */

namespace Transaction\Http\Services;

use App\Consts;
use App\Http\Services\Blockchain\CoinConfigs;
use App\Notifications\WithdrawalVerifyAlert;
use App\Utils;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Transaction\Models\Transaction;
use Transaction\Utils\BalanceCalculate;
use Transaction\Utils\Checker;

/**
 * Class WithdrawalService
 * @package Transaction\Http\Services
 */
class WithdrawalService
{
    /**
     * @var WalletService
     */
    private $walletService;

    private $transactionService;

    /**
     * WithdrawalService constructor.
     * @param WalletService $walletService
     * @param TransactionService $transactionService
     */
    public function __construct(
        WalletService $walletService,
        TransactionService $transactionService
    ) {
        $this->walletService = $walletService;
        $this->transactionService = $transactionService;
    }

    /**
     * @param $user
     * @param $currency
     * @param $params
     * @return Transaction
     */
    public function withdraw($user, $currency, $params)
    {
        $withdrawLimit = $this->transactionService->getWithdrawLimit($currency);
        $networkId = $params['network_id'];
        $networkCoin = DB::table('coins', 'c')
            ->join('network_coins as nc', 'c.id', 'nc.coin_id')
            ->join('networks as n', 'nc.network_id', 'n.id')
            ->where([
                'c.coin' => $currency,
                'nc.network_id' => $networkId,
                'n.enable' => true,
                'nc.network_enable' => true,
                'n.network_withdraw_enable' => true,
                'nc.network_withdraw_enable' => true,
            ])
            ->selectRaw('n.*, nc.withdraw_fee, nc.min_withdraw as minium_withdrawal')
            ->first();
        if (!$networkCoin) {
            throw ValidationException::withMessages([
                //'fee' => [Consts::WITHDRAW_ERROR_FEE_WITHDRAW],
                'network' => 'network_not_support',
            ]);
        } else {
            $withdrawLimit->fee = $networkCoin->withdraw_fee;
            $withdrawLimit->minium_withdrawal = $networkCoin->minium_withdrawal;
        }

        app(ValidateWithdrawService::class)->validator($user, $params, $withdrawLimit);
        $transaction = $this->createWithdrawTransaction($user, $currency, $params, $withdrawLimit);
        $balancePay = BalanceCalculate::approvedWithdraw($transaction);
        $isSpotMainBalance = env('DEPOSIT_WITHDRAW_SPOT_BALANCE', false);
        if ($isSpotMainBalance) {
            $this->transactionService->sendMEDepositWithdraw($transaction, $user->id, $currency, $balancePay, false, false);
        }

        $this->walletService->updateUserBalanceRaw($currency, $user->id, 0, $balancePay);
        $this->transactionService->notifyTransactionCreated($transaction);
        $user->notify(new WithdrawalVerifyAlert($transaction));

        return $transaction;
    }

    /**
     * @param $user
     * @param $currency
     * @param $params
     * @param $withdrawLimit
     * @return Transaction
     */
    private function createWithdrawTransaction($user, $currency, $params, $withdrawLimit)
    {
        $toAddress = $this->getTransactionAddress($params);

        $transaction = new Transaction();
        $networkId = \Arr::get($params, 'network_id');

        $data = [
            'transaction_id' => Amanpuri_unique(),
            'user_id' => $user->id,
            'currency' => $currency,
            'network_id' => $networkId,
            'amount' => \Arr::get($params, 'amount'),
            'fee' => $withdrawLimit->fee,
            'from_address' => $this->getUserAddress($user->id, $currency, $networkId),
            'to_address' => $toAddress,
            'blockchain_sub_address' => \Arr::get($params, 'blockchain_sub_address'),
            'status' => Consts::TRANSACTION_STATUS_PENDING,
            'transaction_date' => Carbon::now(),
            'is_external' => Checker::getTypeTransaction($currency, $toAddress, $networkId),
            'created_at' => Utils::currentMilliseconds(),
            'updated_at' => Utils::currentMilliseconds(),
        ];

        $transaction->fill($data);
        $transaction->save();

        return $transaction;
    }

    private function getUserAddress($userId, $currency, $networkId)
    {
        return DB::table('user_blockchain_addresses')
            ->where('currency', $currency)
            ->where('network_id', $networkId)
            ->where('user_id', $userId)
            ->value('blockchain_address');
        //return \DB::table($currency . '_accounts')->where('id', $userId)->value('blockchain_address');
    }

    private function getTransactionAddress($params)
    {
        $currency = $params['currency'];
        if ($currency === Consts::CURRENCY_USD) {
            return $params['foreign_bank_account'];
        } elseif (CoinConfigs::getCoinConfig($currency, $params['network_id'])->isTagAttached()) {
            return $params['blockchain_address'] . Consts::XRP_TAG_SEPARATOR . $params['blockchain_sub_address'];
        } else {
            return $params['blockchain_address'];
        }
    }
}
