<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\TransactionService;
use App\Jobs\SendBalance;
use App\Jobs\SendBalanceLogToWallet;
use App\Models\AdminKrwBankAccount;
use App\Models\BankName;
use App\Models\KrwSetting;
use App\Models\KrwTransaction;
use App\Utils;
use App\Utils\BigNumber;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;

class KRWDepositWithdrawController extends AppBaseController
{
    private TransactionService $transactionService;
    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    public function getBankNames()
    {
        return $this->sendResponse(BankName::all());
    }

    public function getBankAccounts(Request $request)
    {
        $input = $request->all();
        $limit = Arr::get($input, 'limit', 0);
        $bankAccounts = AdminKrwBankAccount::when($limit > 0,
            function ($query) use ($limit) {
                return $query->paginate($limit);
            }, function ($query) {
                return $query->get();
            });

        return $this->sendResponse($bankAccounts);
    }

    public function storeBankAccount(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'bank_id' => 'required|integer|exists:bank_names,id',
                'account_no' => 'required|string|max:64',
                'account_name' => 'required|string|max:64'
            ]);

            if ($validator->fails()) {
                return $this->sendError($validator->errors());
            }

            $bankAccountData = $request->only(['bank_id', 'account_no', 'account_name']);
            $bankAccountData['status'] = 'enable';
            $bankAccount = AdminKrwBankAccount::create($bankAccountData);


            return $this->sendResponse($bankAccount->load('bankName'));
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function updateBankAccount($id, Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'bank_id' => 'required|integer|exists:bank_names,id',
                'account_no' => 'required|string|max:64',
                'account_name' => 'required|string|max:64'
            ]);

            if ($validator->fails()) {
                return $this->sendError($validator->errors());
            }

            $bankAccount = AdminKrwBankAccount::find($id);
            if (!$bankAccount) {
                return $this->sendError('Bank account not found');
            }

            $bankAccountData = $request->only(['bank_id', 'account_no', 'account_name']);
            $bankAccount->update($bankAccountData);
            $bankAccount->refresh();


            return $this->sendResponse($bankAccount->load('bankName'));
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function destroyBankAccount($id)
    {
        try {
            $bankAccount = AdminKrwBankAccount::find($id);
            if (!$bankAccount) {
                return $this->sendError('Bank account not found');
            }

            $bankAccount->delete();
            return $this->sendResponse(true,"success");
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError($e->getMessage());
        }
    }

    public function getSettings()
    {
        return $this->sendResponse(KrwSetting::whereIn('key', ['deposit_fee', 'withdrawal_fee'])->get());
    }

    public function updateSettings(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'deposit_fee' => 'required|integer|min:0|max:100',
                'withdrawal_fee' => 'required|integer|min:0|max:100',
            ]);

            if ($validator->fails()) {
                return $this->sendError($validator->errors());
            }

            $depositFee = $request->deposit_fee ?? 0;
            $withdrawalFee = $request->withdrawal_fee ?? 0;
            DB::beginTransaction();

            KrwSetting::updateOrCreate(
                ['key' => 'deposit_fee'],
                ['key' => 'deposit_fee', 'value' => $depositFee]
            );

            KrwSetting::updateOrCreate(
                ['key' => 'withdrawal_fee'],
                ['key' => 'withdrawal_fee', 'value' => $withdrawalFee]
            );

            DB::commit();
            return $this->sendResponse(true,"success");
        } catch (Exception $e) {
            Log::error($e);
            DB::rollBack();
            return $this->sendError($e->getMessage());
        }
    }

    public function getTransactions(Request $request)
    {
        $input = $request->all();
        $limit = Arr::get($input, 'limit', 0);

        $transactions = KrwTransaction::with(['user:id,uid,name,email'])
            ->filter($input)
            ->orderBy('id', 'desc')
            ->when($limit > 0, function ($query) use ($limit) {
                return $query->paginate($limit);
            }, function ($query) {
                return $query->get();
            });
        return $this->sendResponse($transactions);
    }

    public function confirmKRWTransaction(Request $request)
    {
        DB::beginTransaction();
        try {
            $transactionId = $request->transaction_id ?? 0;
            $transaction = KrwTransaction::find($transactionId);

            if (!$transaction || $transaction->status != Consts::TRANSACTION_STATUS_PENDING) {
                throw new HttpException(422, __('exception.invalid_transaction'));
            }

            $transaction->update([
                'status' => Consts::TRANSACTION_STATUS_SUCCESS
            ]);

            $amount = $transaction->amount_usdt;
            $typeDeposit = $transaction->type == Consts::TRANSACTION_TYPE_DEPOSIT;
            if (!$typeDeposit) {
                $amount = BigNumber::new($amount)->mul(-1)->toString();
            }
            $userBalance = $this->transactionService->getAndLockUserBalance(Consts::CURRENCY_USDT, $transaction->user_id);
            $dataUpdate = ['balance' => DB::raw('balance + ' . $amount)];

            if ($typeDeposit) {
                $dataUpdate['available_balance'] = DB::raw('available_balance + ' . $amount);
            }

            DB::table($this->transactionService->getTableTypeDepositWithdraw(Consts::CURRENCY_USDT))
                ->where('id', $userBalance->id)
                ->update($dataUpdate);

            if (env('DEPOSIT_WITHDRAW_SPOT_BALANCE', false)) {
                // send balance to ME
                if ($typeDeposit) {
                    $this->transactionService->sendMEKRWDepositWithdraw($transaction, $transaction->user_id, Consts::CURRENCY_USDT, $transaction->amount_usdt, $typeDeposit);
                }

                // send balance to wallet
                if (env('SEND_BALANCE_LOG_TO_WALLET', false)) {
                    $currencyAmount = $transaction->amount_usdt;
                    $currencyAmountWithoutFee = BigNumber::new($transaction->amount_usdt)->sub($transaction->fee)->toString();
                    $currencyFeeAmount = $transaction->fee;
                    if ($typeDeposit) {
                        $currencyFeeAmount = "0";
                        $currencyAmount = $transaction->amount_usdt;
                        $currencyAmountWithoutFee = $transaction->amount_usdt;
                    }
                    SendBalanceLogToWallet::dispatch([
                        'userId' => $transaction->user_id,
                        'walletType' => 'SPOT',
                        'type' => $typeDeposit ? 'DEPOSIT' : 'WITHDRAWAL',
                        'currency' => Consts::CURRENCY_USDT,
                        'currencyAmount' => $currencyAmount,
                        'currencyFeeAmount' => $currencyFeeAmount,
                        'currencyAmountWithoutFee' => $currencyAmountWithoutFee,
                        'date' => Utils::currentMilliseconds()
                    ])->onQueue(Consts::QUEUE_BALANCE_WALLET);
                }
            }


            SendBalance::dispatchIfNeed($transaction->user_id, [Consts::CURRENCY_USDT], Consts::TYPE_MAIN_BALANCE);

            DB::commit();
            return $this->sendResponse($transaction->refresh());

        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }

    public function rejectKRWTransaction(Request $request)
    {

        DB::beginTransaction();
        try {
            $transactionId = $request->transaction_id ?? 0;
            $transaction = KrwTransaction::find($transactionId);

            if (!$transaction || $transaction->status != Consts::TRANSACTION_STATUS_PENDING) {
                throw new HttpException(422, __('exception.invalid_transaction'));
            }

            $transaction->update([
                'status' => Consts::TRANSACTION_STATUS_REJECTED
            ]);

            $amount = $transaction->amount_usdt;
            $typeDeposit = $transaction->type == Consts::TRANSACTION_TYPE_DEPOSIT;
            if (!$typeDeposit) {

                $userBalance = $this->transactionService->getAndLockUserBalance(Consts::CURRENCY_USDT, $transaction->user_id);
                $dataUpdate = ['available_balance' => DB::raw('available_balance + ' . $amount)];

                DB::table($this->transactionService->getTableTypeDepositWithdraw(Consts::CURRENCY_USDT))
                    ->where('id', $userBalance->id)
                    ->update($dataUpdate);

                if (env('DEPOSIT_WITHDRAW_SPOT_BALANCE', false)) {
                    // send balance to ME
                    $this->transactionService->sendMEKRWDepositWithdraw($transaction, $transaction->user_id, Consts::CURRENCY_USDT, $transaction->amount_usdt, false, true);
                }
                SendBalance::dispatchIfNeed($transaction->user_id, [Consts::CURRENCY_USDT], Consts::TYPE_MAIN_BALANCE);
            }

            DB::commit();
            return $this->sendResponse($transaction->refresh());

        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }

        DB::beginTransaction();
        try {
            $transaction = $this->transactionService->rejectUsdTransaction($request->all());
            DB::commit();
            $this->notifyTransactionStatus($transaction);
            return $this->sendResponse($transaction);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return $this->sendError($ex->getMessage());
        }
    }
}
