<?php

namespace App\Http\Controllers\API;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Http\Services\InquiryService;
use App\Http\Services\TransactionService;
use App\Models\AdminKrwBankAccount;
use App\Models\BankName;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Inquiry;
use App\Models\InquiryType;
use App\Models\KrwSetting;
use App\Models\KrwTransaction;
use App\Utils\BigNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;

class KRWDepositWithdrawController extends AppBaseController
{
    private TransactionService $transactionService;
    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    public function getSettings()
    {
        return $this->sendResponse(KrwSetting::get(['key', 'value']));
    }

    public function getBankNames()
    {
        return $this->sendResponse(BankName::get(['id', 'code', 'name']));
    }

    public function getBankAccounts(Request $request)
    {
        $input = $request->all();
        //$limit = Arr::get($input, 'limit', 0);
        $bankAccounts = AdminKrwBankAccount::with(['bankName:id,code,name'])
            ->where('status', 'enable')
            ->select(['id', 'bank_id', 'account_no', 'account_name'])
            ->get();

        return $this->sendResponse($bankAccounts);
    }

    public function getTransactions(Request $request)
    {
        $user = $request->user();
        $input = $request->all();
        $limit = Arr::get($input, 'limit', 0);

        $transactions = KrwTransaction::filter($input)
            ->where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->when($limit > 0, function ($query) use ($limit) {
                return $query->paginate($limit);
            }, function ($query) {
                return $query->get();
            });
        return $this->sendResponse($transactions);
    }

    public function depositKRW(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'bank_account_id' => 'required|integer|exists:admin_krw_bank_accounts,id',
            'amount_krw' => 'required|numeric|min:0',
            'amount_usdt' => 'required|numeric|min:0',
            'fee' => 'required|numeric|min:0',
            'exchange_rate' => 'required|numeric|min:0',

        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        try {
            $bankAccountId = $request->bank_account_id ?? 0;
            $amountKrw = $request->amount_krw ?? 0;
            $amountUsdt = $request->amount_usdt ?? 0;
            $fee = $request->fee ?? 0;
            $exchangeRate = $request->exchange_rate ?? 0;

            $bankAccount = AdminKrwBankAccount::find($bankAccountId);
            if (!$bankAccount) {
                throw new HttpException(422, 'bank_account_not_exist');
            }

            if (BigNumber::new($amountKrw)->comp(0) <= 0 || BigNumber::new($amountUsdt)->comp(0) <= 0) {
                throw new HttpException(422, Consts::DEPOSIT_ERROR_EXIST_AMOUNT_INVALID);
            }

            $exchangeRateNow = KrwSetting::where('key', 'exchange_rate')->first();
            if (!$exchangeRateNow) {
                throw new HttpException(422, 'exchange_rate_not_get');
            }

            $checkExchangeRate = BigNumber::new($exchangeRateNow->value)->sub($exchangeRate)->toString();
            if ($checkExchangeRate > 1 || $checkExchangeRate < -1) {
                throw new HttpException(422, 'exchange_rate_fail');
            }

            // check amount usdt
            $checkAmountUsdt = BigNumber::new($amountKrw)->div($exchangeRate)->sub($fee)->sub($amountUsdt)->toString();
            if ($checkAmountUsdt > 1 || $checkAmountUsdt < -1) {
                throw new HttpException(422, 'amount_usdt_deposit_invalid');
            }

            $transaction = KrwTransaction::create([
                'user_id' => $user->id,
                'type' => Consts::TRANSACTION_TYPE_DEPOSIT,
                'bank_name' => $bankAccount->bankName->name,
                'account_name' => $bankAccount->account_name,
                'account_no' => $bankAccount->account_no,
                'exchange_rate' => $exchangeRate,
                'amount_usdt' => $amountUsdt,
                'amount_krw' => $amountKrw,
                'fee' => $fee,
                'status' => Consts::TRANSACTION_STATUS_PENDING
            ]);
            return $this->sendResponse($transaction);

        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage());
        }
    }

    public function withdrawKRW(Request $request)
    {
        $user = $request->user();
        $rules = [
            'bank_name' => 'required|string|max:100',
            'account_name' => 'required|string|max:100',
            'account_no' => 'required|string|max:100',
            'amount_usdt' => 'required|numeric|min:0',
            'fee' => 'required|numeric|min:0',

        ];

        if ($user->hasOTP()) {
            $rules['otp'] = 'required|otp_not_used|correct_otp';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        DB::beginTransaction();
        try {
            $bankName = $request->bank_name ?? '';
            $accountName = $request->account_name ?? '';
            $accountNo = $request->account_no ?? '';
            $amountUsdt = $request->amount_usdt ?? 0;
            $fee = $request->fee ?? 0;

            $exchangeRateNow = KrwSetting::where('key', 'exchange_rate')->first();
            if (!$exchangeRateNow) {
                throw new HttpException(422, 'exchange_rate_not_get');
            }

            $setting = DB::table('user_security_settings')->where('id', $user->id)->first();
            if (!$setting) {
                throw new HttpException(422, 'user_setting_not_get');
            }

            if (!$setting->identity_verified) {
                throw new HttpException(401, 'messages.user_cannot_withdraw_fiat');
            }

            if (!$user->google_authentication) {
                throw new HttpException(401, 'messages.user_enable_google_authen');
            }

            $userBalance = $this->transactionService->getAndLockUserBalance(Consts::CURRENCY_USDT, $user->id);

            if (!$userBalance) {
                throw new HttpException(406, Consts::WITHDRAW_ERROR_NOT_ENOUGH_BALANCE);
            }

            $availableBalance = $userBalance->available_balance;
            if (BigNumber::new($amountUsdt)->comp($availableBalance) > 0) {
                throw new HttpException(406, Consts::WITHDRAW_ERROR_NOT_ENOUGH_BALANCE);
            }

            $amountKrw = BigNumber::round(BigNumber::new($amountUsdt)->sub($fee)->mul($exchangeRateNow->value), BigNumber::ROUND_MODE_HALF_UP, 2);

            $transaction = KrwTransaction::create([
                'user_id' => $user->id,
                'type' => Consts::TRANSACTION_TYPE_WITHDRAW,
                'bank_name' => $bankName,
                'account_name' => $accountName,
                'account_no' => $accountNo,
                'exchange_rate' => $exchangeRateNow->value,
                'amount_usdt' => $amountUsdt,
                'amount_krw' => $amountKrw,
                'fee' => $fee,
                'status' => Consts::TRANSACTION_STATUS_PENDING
            ]);

            // update balance usdt
            DB::table($this->transactionService->getTableTypeDepositWithdraw(Consts::CURRENCY_USDT))
                ->where('id', $userBalance->id)
                ->update(['available_balance' => DB::raw('available_balance - ' . $amountUsdt)]);

            if (env('DEPOSIT_WITHDRAW_SPOT_BALANCE', false)) {
                $this->transactionService->sendMEKRWDepositWithdraw($transaction, $transaction->user_id, Consts::CURRENCY_USDT, $transaction->amount_usdt, false);
            }


            DB::commit();
            return $this->sendResponse($transaction);

        } catch (Exception $ex) {
            DB::rollBack();
            return $this->sendError($ex->getMessage());
        }
    }
}
