<?php

namespace App\Http\Services;

use App\Consts;
use App\Events\MainBalanceUpdated;
use App\Events\TransactionCreated;
use App\Http\Services\Blockchain\SotatekBlockchainService;
use App\Jobs\SendBalance;
use App\Jobs\SendBalanceLogToWallet;
use App\Jobs\SendFutureFirebaseNotification;
use App\Jobs\SendNotifyTelegram;
use App\Jobs\UpdateUserTransaction;
use App\Jobs\Withdraw;
use App\Models\Admin;
use App\Models\AdminBankAccount;
use App\Models\CoinsConfirmation;
use App\Models\SpotCommands;
use App\Models\UsdTransaction;
use App\Notifications\TransactionCreated as NotificationTransactionCreated;
use App\Notifications\WithdrawalCanceledNotification;
use App\Notifications\DepositAlerts;
use App\Notifications\WithdrawAlerts;
use App\Notifications\WithdrawalVerifyAlert;
use App\Repositories\TransactionRepository;
use App\Models\User;
use App\Utils;
use App\Utils\BigNumber;
use Carbon\Carbon;
use App\Facades\CheckFa;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Transaction\Jobs\WithdrawJob;
use Transaction\Models\Transaction;
use App\Notifications\AdminUsdAlerts;
use Transaction\Http\Services\WithdrawVerifyService;
use Transaction\Http\Services\TransactionService as TransactionServiceClient;
use Transaction\Http\Services\UserBalanceService;
use App\Events\WithdrawDepositBalanceEvent;
use const _PHPStan_532094bc1\__;

class TransactionService
{
    private $transactionRepository;
    private $userService;
    private $priceService;
    private $masterDataService;
    private $withdrawVerifyService;

    public function __construct()
    {
        $this->transactionRepository = new TransactionRepository();
        $this->userService = new UserService();
        $this->priceService = new PriceService();
        $this->masterDataService = new MasterdataService();
        $transactionServiceClient = new TransactionServiceClient();
        $this->withdrawVerifyService = new WithdrawVerifyService($transactionServiceClient);
    }

    /**
     * Paginate transaction history of an account of an user
     *
     * @param $params
     * @param int $limit
     * @return mixed
     */
    public function getHistory($params, $limit = Consts::DEFAULT_PER_PAGE): mixed
    {
        return $this->transactionRepository->getHistory($params, $limit);
    }

    public function getUserTransactions($params)
    {
        return $this->transactionRepository->getUserTransactions($params);
    }

    /**
     * Get all transaction history of an account of an user
     *
     * @param $params
     * @return mixed
     */
    public function exportHistory($params): mixed
    {
        return $this->transactionRepository->exportHistory($params);
    }

    /**
     * @param $params
     * @return mixed
     * @throws Exception
     */
    public function withdraw($params): mixed
    {
        $transaction = null;
        $currency = $params['currency'];
        $result = CheckFa::withdraw($currency);
        if ($result === 0) {
            throw new HttpException(401, trans('exception.is_withdraw'));
        }
        $user = Auth::user();
        $userBalance = $this->getAndLockUserBalance($currency, $user->id);
        $withdrawLimit = $this->getWithdrawLimit($user, $currency);

        $this->validateWithdraw($params, $withdrawLimit, $userBalance);

        $params['created_at'] = Utils::currentMilliseconds();
        $params['updated_at'] = $params['created_at'];
        $params['transaction_date'] = Carbon::now();
        $params['fee'] = $withdrawLimit->fee;

        $params = escapse_string_params($params);

        $transactionAddress = $this->getTransactionAddress($params);
        $targetUserId = $this->userService->getUserIdFromAddress($transactionAddress, $currency);
        if ($targetUserId) {
            $transaction = $this->withdrawInternally($params, $userBalance, $targetUserId);
        } else {
            //withdraw to external address
            $transaction = $this->transactionRepository->create($params);
            $this->updateUserBalance($userBalance, $currency, $transaction); // update balance and available balance
            $this->createUserTransaction($transaction);
            $this->notifyTransactionCreated($transaction);

            Withdraw::dispatch($transaction)->onQueue(Consts::QUEUE_WITHDRAW);
        }

        return $transaction;
    }

    /**
     * @param $params
     * @return null
     * @throws Exception
     */
    public function withdrawUsd($params)
    {
        if (!$this->isWithdrawableFiat()) {
            throw new HttpException(401, 'messages.user_cannot_withdraw_fiat');
        }

        if (!$this->isEnableGoogleAuthen()) {
            throw new HttpException(401, 'messages.user_enable_google_authen');
        }

        $coinsConfirmation = $this->getCoinsConfirmation('usd');
        if (!$coinsConfirmation->is_withdraw) {
            throw new HttpException(401, 'messages.disable_coin_msg');
        }

        $user = Auth::user();
        $transaction = null;
        $currency = 'usd';
        // $originalAmount = Arr::get($params, 'amount', 0);
        // $withdrawLimit = $this->getWithdrawFiatLimit($user, $currency, $originalAmount);
        // $amount = BigNumber::new($originalAmount)->add($withdrawLimit->fee)->toString();

        $userBalance = $this->getAndLockUserBalance($currency, $user->id);
        $withdrawLimit = $this->getWithdrawLimit($user, $currency);
        $amount = Arr::get($params, 'amount', 0);
        // $withdrawLimit = $this->getWithdrawFiatLimit($user, $currency, $amount);

        $this->validateWithdrawUsd($amount, $withdrawLimit, $userBalance, $params);

        $params['user_id'] = $user->id;
        $params['created_at'] = Utils::currentMilliseconds();
        $params['updated_at'] = $params['created_at'];
        $params['fee'] = $withdrawLimit->fee;
        $params['status'] = Consts::TRANSACTION_STATUS_PENDING;

        $params = escapse_string_params($params);

        $transaction = UsdTransaction::create($params);

        $this->updateUserBalance($userBalance, $currency, $transaction, false, true); // update availabel balance

        $user_data = DB::table('usd_accounts')->where('id', $transaction->user_id)->first();
        event(new MainBalanceUpdated($transaction->user_id,
            ['total_balance' => $user_data->balance, 'available_balance' => $user_data->available_balance]));

        $this->fireEventBalanceChanged($transaction->user_id, $currency);
        $this->fireEventTransactionCreated($transaction->user_id, $transaction);

        return $transaction;
    }

    // private function getFiatWithdrawFee($withdrawLimit, $amount)
    // {
    //     return BigNumber::new(-1, BigNumber::ROUND_MODE_HALF_UP, 2)->mul($amount)->mul($withdrawLimit->fee)->toString();
    // }

    public function isWithdrawableFiat()
    {
        $setting = DB::table('user_security_settings')->where('id', Auth::id())->first();
        return $setting->identity_verified;
    }

    public function isEnableGoogleAuthen(): bool
    {
        $user = \App\Models\User::where('id', Auth::id())->first();
        if (!$user->google_authentication) {
            return false;
        }
        return true;
    }

    /**
     * @param $amount
     * @param $withdrawLimit
     * @param $userBalance
     * @throws Exception
     */
    private function validateWithdrawUsd($amount, $withdrawLimit, $userBalance, $params): void
    {
        if (!BigNumber::new($amount)->isNegative() && BigNumber::new($amount)->comp(0) != 0) {
            throw new HttpException(406, Consts::WITHDRAW_ERROR_AMOUNT_WITHDRAW_IS_POSITIVE);
        }

        $minAmount = BigNumber::new($amount)->sub($withdrawLimit->fee)->toString();
        if ($this->checkMinimumWithdrawal($minAmount, $withdrawLimit)) {
            throw new HttpException(406, Consts::WITHDRAW_ERROR_MINIMUM_WITHDRAW);
        }

        if ($this->checkFeeWithdrawal($params, $withdrawLimit)) {
            throw new HttpException(406, Consts::WITHDRAW_ERROR_FEE_WITHDRAW);
        }

        if ($this->checkLimitWithdrawal($params, $withdrawLimit)) {
            throw new HttpException(406, Consts::WITHDRAW_ERROR_LIMIT_WITHDRAW);
        }

        if ($this->checkDailyLimitWithdrawal($params, $withdrawLimit)) {
            throw new HttpException(406, Consts::WITHDRAW_ERROR_DAILY_LIMIT_WITHDRAW);
        }

        if ($this->checkMinWithdrawal($params, $withdrawLimit)) {
            throw new HttpException(406, Consts::WITHDRAW_ERROR_MINIMUM_WITHDRAW);
        }

        if (!$this->isUserBalanceEnough($userBalance, $amount, $withdrawLimit)) {
            throw new HttpException(406, Consts::WITHDRAW_ERROR_NOT_ENOUGH_BALANCE);
        }

        $totalWithdrawDaily = $this->getUsdWithdrawDaily();

        if (BigNumber::new(-1)->mul($amount)->add($totalWithdrawDaily)->comp($withdrawLimit->daily_limit) > 0) {
            throw new HttpException(406, Consts::WITHDRAW_ERROR_OVER_DAILY_LIMIT);
        }

        if (BigNumber::new(-1)->mul($amount)->comp($withdrawLimit->limit) > 0) {
            throw new HttpException(406, Consts::WITHDRAW_ERROR_OVER_ONE_TIME_LIMIT);
        }
    }

    public function getUsdTransactions($params)
    {
        $type = Arr::get($params, 'type');
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        $select = [
            'usd_transactions.id',
            'users.email',
            'usd_transactions.created_at',
            'usd_transactions.bank_name',
            'usd_transactions.bank_branch',
            'usd_transactions.account_name',
            'usd_transactions.account_no',
            'usd_transactions.amount',
            'usd_transactions.status',
            'usd_transactions.code'
        ];
        return $this->buildFiatUsdTransactionQuery($params, $type)->select(...$select)->paginate($limit);
    }

    private function buildFiatUsdTransactionQuery($params, $type)
    {
        $conditionAmount = $type === Consts::TRANSACTION_TYPE_WITHDRAW ? '<' : '>';
        return UsdTransaction::join('users', 'users.id', 'usd_transactions.user_id')
            ->where('amount', $conditionAmount, 0)
            ->when(!empty($params['start_date']), function ($query) use ($params) {
                return $query->where('usd_transactions.created_at', '>=', $params['start_date']);
            })
            ->when(!empty($params['end_date']), function ($query) use ($params) {
                return $query->where('usd_transactions.created_at', '<', $params['end_date']);
            })
            ->when(!empty($params['status']) && $params['status'] === 'history', function ($query) {
                return $query->whereIn('usd_transactions.status', [
                    Consts::TRANSACTION_STATUS_SUCCESS,
                    Consts::TRANSACTION_STATUS_REJECTED
                ]);
            }, function ($query) use ($params) {
                return $query->where('usd_transactions.status', $params['status']);
            })
            ->when(!empty($params['search_key']), function ($query) use ($params) {
                $searchKey = $params['search_key'];
                return $query->where(function ($q) use ($searchKey) {
                    $q->where('users.email', 'like', '%' . $searchKey . '%');
                });
            })
            ->when(!empty($params['sort']), function ($query) use ($params) {
                switch ($params['sort']) {
                    case "usd_transactions.amount":
                        $sortType = $params['sort_type'] === 'asc' ? 'desc' : 'asc';
                        return $query->orderBy($params['sort'], $sortType);
                    default:
                        return $query->orderBy($params['sort'], $params['sort_type']);
                }
            }, function ($query) use ($params) {
                if (!empty($params['status']) && $params['status'] === 'all') {
                    return $query->orderBy('status');
                }
                $query = $query->orderBy('usd_transactions.created_at', 'desc');
                return $query;
            });
    }

    private function buildFiatWithdrawalQuery($params)
    {
        return Transaction::join('users', 'users.id', 'transactions.user_id')
            ->where('currency', $params['currency'])
            ->filterWithdraw()
            ->when(array_key_exists('start', $params), function ($query) use ($params) {
                return $query->where('transactions.created_at', '>=', $params['start']);
            })
            ->when(array_key_exists('end', $params), function ($query) use ($params) {
                return $query->where('transactions.created_at', '<', $params['end']);
            })
            ->when($params['status'] === 'all', function ($query) {
                return $query->whereIn('transactions.status', [
                    Consts::TRANSACTION_STATUS_SUCCESS,
                    Consts::TRANSACTION_STATUS_PENDING
                ]);
            }, function ($query) use ($params) {
                return $query->where('transactions.status', $params['status']);
            })
            ->when(array_key_exists('search_key', $params), function ($query) use ($params) {
                $searchKey = $params['search_key'];
                return $query->where(function ($q) use ($searchKey) {
                    $q->where('users.email', 'like', '%' . $searchKey . '%')
                        ->orWhere('transactions.bank_name', 'like', '%' . $searchKey . '%')
                        ->orWhere('transactions.foreign_bank_account_holder', 'like', '%' . $searchKey . '%')
                        ->orWhere('transactions.foreign_bank_account', 'like', '%' . $searchKey . '%');
                });
            })
            ->when(array_key_exists('sort', $params) && !empty($params['sort']), function ($query) use ($params) {
                switch ($params['sort']) {
                    case "transactions.amount":
                        $sortType = $params['sort_type'] === 'asc' ? 'desc' : 'asc';
                        return $query->orderBy($params['sort'], $sortType);
                    default:
                        return $query->orderBy($params['sort'], $params['sort_type']);
                }
            }, function ($query) use ($params) {
                if ($params['status'] === 'all') {
                    return $query->orderBy('status');
                }
                $query = $query->orderBy('transactions.created_at', 'desc');
                return $query;
            });
    }

    private function buildFiatDepositQuery($params)
    {
        return Transaction::join('users', 'users.id', 'transactions.user_id')
            ->where('currency', $params['currency'])
            ->filterDeposit()
            ->when(array_key_exists('start', $params), function ($query) use ($params) {
                return $query->where('transactions.created_at', '>=', $params['start']);
            })
            ->when(array_key_exists('end', $params), function ($query) use ($params) {
                return $query->where('transactions.created_at', '<', $params['end']);
            })
            ->when($params['status'] === 'all', function ($query) {
                return $query->whereIn('transactions.status', [
                    Consts::TRANSACTION_STATUS_SUCCESS,
                    Consts::TRANSACTION_STATUS_PENDING,
                    Consts::TRANSACTION_STATUS_REJECTED
                ]);
            }, function ($query) use ($params) {
                return $query->where('transactions.status', $params['status']);
            })
            ->when(array_key_exists('search_key', $params), function ($query) use ($params) {
                $searchKey = $params['search_key'];
                return $query->where(function ($q) use ($searchKey) {
                    $q->where('users.email', 'like', '%' . $searchKey . '%')
                        ->orWhere('transactions.bank_name', 'like', '%' . $searchKey . '%')
                        ->orWhere('transactions.foreign_bank_account_holder', 'like', '%' . $searchKey . '%')
                        ->orWhere('transactions.foreign_bank_account', 'like', '%' . $searchKey . '%');
                });
            })
            ->when(array_key_exists('sort', $params) && !empty($params['sort']), function ($query) use ($params) {
                return $query->orderBy($params['sort'], $params['sort_type']);
            }, function ($query) {
                return $query->orderByRaw("CASE WHEN transactions.status='pending' THEN 1 ELSE 2 END ASC")
                    ->orderBy('transactions.updated_at', 'desc');
            });
    }

    public function exportUsdTransactions($params)
    {
        return $this->buildFiatWithdrawalQuery($params)->select(
            'users.email',
            'transactions.created_at',
            'transactions.created_at',
            'transactions.foreign_bank_account_holder',
            'transactions.bank_name',
            'transactions.foreign_bank_account',
            'transactions.amount',
            'transactions.status'
        )->get();
    }

    public function getUsdTransactionCount($params)
    {
        $type = Arr::get($params, 'type', '');
        if ($type == Consts::TRANSACTION_TYPE_DEPOSIT) {
            return $this->buildFiatDepositQuery($params)->count();
        } elseif ($type == Consts::TRANSACTION_TYPE_WITHDRAW) {
            return $this->buildFiatWithdrawalQuery($params)->count();
        }
    }

    public function getUsdWithdrawDaily()
    {
        $amountWithdrawDaily = UsdTransaction::where('user_id', Auth::id())
            ->filterWithdraw()
            ->where('status', '!=', Consts::TRANSACTION_STATUS_CANCEL)
            ->where('status', '!=', Consts::TRANSACTION_STATUS_REJECTED)
            ->where('created_at', '>=', Carbon::today()->timestamp * 1000)
            ->sum('amount');

        return abs($amountWithdrawDaily);
    }

    /**
     * @param $params
     * @return mixed
     * @throws Exception
     */
    public function confirmUsdTransaction($params)
    {
        $transaction = UsdTransaction::lockForUpdate()->findOrFail($params['transaction_id']);

        if ($transaction->status != Consts::TRANSACTION_STATUS_PENDING) {
            throw new HttpException(422, __('exception.invalid_transaction'));
        }

        $transaction->update([
            'status' => Consts::TRANSACTION_STATUS_SUCCESS
        ]);

        $userBalance = $this->getAndLockUserBalance(Consts::CURRENCY_USD, $transaction->user_id);

        // only update available_balance for deposit transaction
        // for withdraw transaction, available_balance has been deducted before
        $updateAvailableBalance = BigNumber::new($transaction->amount)->comp(0) > 0 && $transaction->code;

        $this->updateUserBalance($userBalance, Consts::CURRENCY_USD, $transaction, true, $updateAvailableBalance);

        $this->createUserTransaction($transaction);

        $user_data = DB::table('usd_accounts')->where('id', $transaction->user_id)->first();
//        event(new MainBalanceUpdated($transaction->user_id, ['total_balance' => $user_data->balance, 'available_balance' => $user_data->available_balance]));

        $this->fireEventBalanceChanged($transaction->user_id, 'usd');
        $this->fireEventTransactionCreated($transaction->user_id, $transaction);

        return $transaction;
    }

    public function rejectUsdTransaction($params)
    {
        $transaction = UsdTransaction::lockForUpdate()->findOrFail($params['transaction_id']);

        if ($transaction->status != Consts::TRANSACTION_STATUS_PENDING) {
            throw new HttpException(422, __('exception.invalid_transaction'));
        }

        $transaction->update([
            'status' => Consts::TRANSACTION_STATUS_REJECTED
        ]);

        if ($transaction->amount < 0) {
            $userBalance = $this->getAndLockUserBalance(Consts::CURRENCY_USD, $transaction->user_id);

            $updateAvailableBalance = BigNumber::new($transaction->amount)->abs();

            $this->refundUserBalance($userBalance, Consts::CURRENCY_USD, $transaction, true, $updateAvailableBalance);
        }

        $user_data = DB::table('usd_accounts')->where('id', $transaction->user_id)->first();
        event(new MainBalanceUpdated($transaction->user_id,
            ['total_balance' => $user_data->balance, 'available_balance' => $user_data->available_balance]));

        $this->fireEventTransactionCreated($transaction->user_id, $transaction);

        return $transaction;
    }

    public function sendTransaction($params)
    {
        return $this->withdrawVerifyService->adminSendVerifyWithdrawEmail($params['transaction_id']);
    }

    public function cancelTransactionWhenRequestToWalletFail($params)
    {
        $transaction = Transaction::lockForUpdate()->where('transaction_id',
            $params['transaction_id'])->filterWithdraw()->first();

        if ($transaction == null) {
            throw new HttpException(422, __('exception.invalid_transaction'));
        }

        $userBalance = $this->getAndLockUserBalance($transaction->currency, $transaction->user_id);

        $updateAvailableBalance = BigNumber::new($transaction->amount)->abs();

        $this->refundUserBalance($userBalance, $transaction->currency, $transaction, true, $updateAvailableBalance);

        $transaction->update([
            'status' => Consts::TRANSACTION_STATUS_CANCEL,
            'cancel_at' => Carbon::now()->timestamp * 1000
        ]);
        $amountRefund = BigNumber::new($updateAvailableBalance)->add($transaction->fee)->toString();
        $isSpotMainBalance = env('DEPOSIT_WITHDRAW_SPOT_BALANCE', false);
        if ($isSpotMainBalance) {
            $this->sendMEDepositWithdraw($transaction, $transaction->user_id, $transaction->currency, $amountRefund, false, true);
        }

        $service = new UserBalanceService();
        $data = $service->getBalanceTransactionMain($transaction->currency, $transaction->user_id);
        event(new WithdrawDepositBalanceEvent($transaction->user_id, $data));

        $this->fireEventTransactionCreated($transaction->user_id, $transaction);

        $this->sendWithdrawalCanceledNotification($transaction->user_id, $transaction);

        return $transaction;
    }

    public function cancelTransaction($params)
    {
        $transaction = Transaction::lockForUpdate()->where('transaction_id',
            $params['transaction_id'])->filterWithdraw()->first();

        if ($transaction == null) {
            throw new HttpException(422, __('exception.invalid_transaction'));
        }

        if ($transaction->approved_by != null || $transaction->tx_hash != null) {
            throw new HttpException(422, __('exception.transaction_executed_canceled'));
        }

        $userBalance = $this->getAndLockUserBalance($transaction->currency, $transaction->user_id);

        $updateAvailableBalance = BigNumber::new($transaction->amount)->abs();

        $this->refundUserBalance($userBalance, $transaction->currency, $transaction, true, $updateAvailableBalance);
        $amountRefund = BigNumber::new($updateAvailableBalance)->add($transaction->fee)->toString();
        $isSpotMainBalance = env('DEPOSIT_WITHDRAW_SPOT_BALANCE', false);
        if ($isSpotMainBalance) {
            $this->sendMEDepositWithdraw($transaction, $transaction->user_id, $transaction->currency, $amountRefund, false, true);
        }

        $transaction->update([
            'status' => Consts::TRANSACTION_STATUS_CANCEL,
            'cancel_at' => Carbon::now()->timestamp * 1000
        ]);

        $service = new UserBalanceService();
        $data = $service->getBalanceTransactionMain($transaction->currency, $transaction->user_id);
        event(new WithdrawDepositBalanceEvent($transaction->user_id, $data));

        $this->fireEventTransactionCreated($transaction->user_id, $transaction);

        $this->sendWithdrawalCanceledNotification($transaction->user_id, $transaction);

        return $transaction;
    }

    /**
     * @param $params
     * @param $withdrawLimit
     * @param $userBalance
     * @return bool
     * @throws Exception
     */
    private function validateWithdraw($params, $withdrawLimit, $userBalance)
    {
        $amount = $params['amount'];
        $currency = $params['currency'];

        // transaction amount is negative
        if (!BigNumber::new($amount)->isNegative() && BigNumber::new($amount)->comp(0) != 0) {
            throw new HttpException(422, Consts::WITHDRAW_ERROR_AMOUNT_WITHDRAW_IS_POSITIVE);
        }
        $user = Auth::user();

        if ($user->isEnableWhiteList() && !$user->checkWhiteListAddress(Arr::get($params, 'blockchain_address'))) {
            throw new HttpException(422, Consts::WITHDRAW_ERROR_WHITELIST_ADDRESS);
        }

        // Check minium_withdrawal
        $minAmount = BigNumber::new($amount)->sub($withdrawLimit->fee)->toString();
        if ($this->checkMinimumWithdrawal($minAmount, $withdrawLimit)) {
            throw new HttpException(422, Consts::WITHDRAW_ERROR_MINIMUM_WITHDRAW);
        }

        if ($this->checkFeeWithdrawal($params, $withdrawLimit)) {
            throw new HttpException(422, Consts::WITHDRAW_ERROR_FEE_WITHDRAW);
        }

        if ($this->checkLimitWithdrawal($params, $withdrawLimit)) {
            throw new HttpException(422, Consts::WITHDRAW_ERROR_LIMIT_WITHDRAW);
        }

        if ($this->checkDailyLimitWithdrawal($params, $withdrawLimit)) {
            throw new HttpException(422, Consts::WITHDRAW_ERROR_DAILY_LIMIT_WITHDRAW);
        }

        if ($this->checkMinWithdrawal($params, $withdrawLimit)) {
            throw new HttpException(422, Consts::WITHDRAW_ERROR_MINIMUM_WITHDRAW);
        }


        if (!$this->isUserBalanceEnough($userBalance, $amount, $withdrawLimit)) {
            throw new HttpException(422, Consts::WITHDRAW_ERROR_NOT_ENOUGH_BALANCE);
        }

        // if (BigNumber::new($withdrawLimit->limit)->add($deductAmount)->comp(0) < 0) {
        //     throw new \Exception(Consts::WITHDRAW_ERROR_OVER_LIMIT);
        // }

        // Check total withdraw in day
        if ($this->isWithdrawOverDailyLimit($user, $currency, $amount, $withdrawLimit)) {
            throw new HttpException(422, Consts::WITHDRAW_ERROR_OVER_DAILY_LIMIT);
        }
        return true;
    }

    private function withdrawInternally($params, $userBalance, $targetUserId)
    {
        $params = escapse_string_params($params);
        $currency = $params['currency'];
        $targetUser = User::findOrFail($targetUserId);
        $user = Auth::user();

        $params['tx_hash'] = $targetUser->email;
        $params['status'] = Consts::TRANSACTION_STATUS_SUCCESS;

        $transaction = $this->transactionRepository->create($params);

        $this->updateUserBalance($userBalance, $currency, $transaction);
        $this->createUserTransaction($transaction);

        $depositTransaction = $this->createInternalDepositTransaction($transaction, $user, $targetUserId);
        $receiverBalance = DB::table($currency . '_accounts')
            ->where('id', $targetUserId)
            ->first();
        $this->updateUserBalance($receiverBalance, $currency, $depositTransaction);
        $this->createUserTransaction($depositTransaction);

        $this->notifyTransactionCreated($transaction);
        $this->notifyTransactionCreated($depositTransaction);
        return $transaction;
    }

    private function createInternalDepositTransaction($transaction, $sender, $targetUserId)
    {
        $depositTransaction = $transaction->replicate();
        $depositTransaction->created_at = Utils::currentMilliseconds();
        $depositTransaction->transaction_date = Carbon::now();
        $depositTransaction->updated_at = $depositTransaction->created_at;
        $depositTransaction->user_id = $targetUserId;
        $depositTransaction->amount = BigNumber::new(-1)->mul($depositTransaction->amount)->toString();
        $depositTransaction->fee = 0;
        $depositTransaction->tx_hash = $sender->email;
        $depositTransaction->save();
        return $depositTransaction;
    }

    public function withdrawToExternalAddress(Transaction $transaction)
    {
        $currency = $transaction->currency;

        $blockchainService = new SotatekBlockchainService($currency);
        //TODO: determine the fee for blockchain transaction
        $transaction->tx_hash = $blockchainService->send(
            BigNumber::new(-1)->mul($transaction->amount)->toString(),
            $transaction->blockchain_address,
            $transaction->blockchain_sub_address,
            $transaction->fee
        );
        $transaction->save();

        $this->createUserTransaction($transaction);

        $this->notifyTransactionCreated($transaction);
        return $transaction;
    }

    public function getTableTypeDepositWithdraw($currency)
    {
        $isSpotMainBalance = env('DEPOSIT_WITHDRAW_SPOT_BALANCE', false);
        if ($isSpotMainBalance) {
            return 'spot_' . $currency . '_accounts';
        }

        return $currency . '_accounts';
    }

    public function deposit($address, $tx_hash, $amount, $currency, $networkId, $createdAt = null)
    {
        $userId = $this->userService->getUserIdFromAddress($address, $currency, $networkId);

        //        if (!$userId && $currency === Consts::CURRENCY_BTC) {
        //            $userIdUSDT = $this->userService->getUserIdFromAddress($address, Consts::CURRENCY_USDT);
        //
        //            if ($userIdUSDT) {
        //                return 0;
        //            }
        //        }

        if (!$userId) {
            Log::error("Deposit to a non-existing address: " . $address);
            return 'error';
        }

       /* if (Consts::CURRENCY_XRP === $currency || Consts::CURRENCY_TRX === $currency || Consts::CURRENCY_EOS === $currency) {
            $array_address_input = explode('|', $address);
            $input['to_address'] = $array_address_input[0];
            $input['blockchain_address'] = $array_address_input[0];
            $input['blockchain_sub_address'] = $array_address_input[1];
            $input['collect'] = Consts::DEPOSIT_TRANSACTION_COLLECTED_STATUS;
        } else {*/
            $input['to_address'] = $address;
            $input['blockchain_address'] = $address;
            $input['collect'] = Consts::DEPOSIT_TRANSACTION_OPEN_STATUS;
        //}

        $input['transaction_id'] = Amanpuri_unique();
        $input['is_external'] = 1;
        $input['tx_hash'] = $tx_hash;
        $input['currency'] = $currency;
        $input['network_id'] = $networkId;
        $input['amount'] = $amount;
        $input['user_id'] = $userId;
        $input['fee'] = 0;
        $input['status'] = Consts::TRANSACTION_STATUS_SUCCESS;
        $input['created_at'] = $createdAt;
        $input['updated_at'] = Utils::currentMilliseconds();
        $input['transaction_date'] = Carbon::now();


        $transaction = $this->transactionRepository->create($input);

        $userBalance = DB::table($this->getTableTypeDepositWithdraw($currency))
            ->where('id', $userId)
            ->first();

        $this->updateUserBalance($userBalance, $currency, $transaction);
        $this->createUserTransaction($transaction);
        $isSpotMainBalance = env('DEPOSIT_WITHDRAW_SPOT_BALANCE', false);
        if ($isSpotMainBalance) {
            $this->sendMEDepositWithdraw($transaction, $userId, $currency, $amount, true, false);
            if (env('SEND_BALANCE_LOG_TO_WALLET', false)) {
                SendBalanceLogToWallet::dispatch([
                    'userId' => $userId,
                    'walletType' => 'SPOT',
                    'type' => 'DEPOSIT',
                    'currency' => $currency,
                    'currencyAmount' => $amount,
                    'currencyFeeAmount' => "0",
                    'currencyAmountWithoutFee' => $amount,
                    'date' => Utils::currentMilliseconds()
                ])->onQueue(Consts::QUEUE_BALANCE_WALLET);
            }
        }

        $this->notifyTransactionCreated($transaction);
        $user = User::find($transaction->user_id);
        $locale = $user->getLocale();
        $title = __('title.notification.deposit_success', [], $locale);
        $body = __('body.notification.deposit_success', ['time' => Carbon::now()], $locale);

        FirebaseNotificationService::send($user->id, $title, $body);
        $user->notify(new DepositAlerts($transaction, $transaction->currency));
        // send notify deposit telegram
		SendNotifyTelegram::dispatch('deposit', 'User deposit success: '.$user->email. " ({$transaction->currency}: {$amount})");
        SendFutureFirebaseNotification::dispatch([
            'type' => 'DEPOSIT',
            'data' => [
                'amount' => $amount,
                'coinType' => $transaction->currency,
                'time' => Utils::currentMilliseconds()
            ]
        ]);

        return $transaction;
    }

    public function sendMEDepositWithdraw($transaction, $userId, $coin, $amount, $deposit, $cancelWithdraw) {
        try {
            $matchingJavaAllow = env("MATCHING_JAVA_ALLOW", false);
            if ($matchingJavaAllow) {
                //send kafka ME Deposit
                $type = $deposit ?  1 : ($cancelWithdraw ? 3 : 2);
                $types = [1 => 'deposit', 2 => 'withdrawal', 3 => 'deposit'];
                $typeName = isset($types[$type]) ? $types[$type] : '';
                if ($amount < 0) {
                    $amount = BigNumber::new($amount)->mul(-1)->toString();
                }

                $payload = [
                    'type' => $typeName,
                    'data' => [
                        'userId' => $userId,
                        'coin' => $coin,
                        'amount' => $amount,
                        'transactionId' => $transaction->id
                    ]
                ];

                if ($type == 3) {
                    $payload['data']['cancelWithdrawId'] = $transaction->id;
                }

                $command = SpotCommands::create([
                    'command_key' => md5(json_encode($payload)),
                    'type_name' => $typeName,
                    'user_id' => $userId,
                    'obj_id' => $transaction->id,
                    'payload' => json_encode($payload),

                ]);
                if (!$command) {
                    throw new HttpException(422, 'can not create command');
                }

                $payload['data']['commandId'] = $command->id;
                Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_COMMAND, $payload);

            }
        } catch (Exception $ex) {
            Log::error($ex);
            Log::error("++++++++++++++++++++ sendMEDepositWithdraw: $userId, coin: $coin, amount: $amount (". ($deposit ? "deposit": "withdrawal") . ")");
        }
    }

    public function sendMEKRWDepositWithdraw($transaction, $userId, $coin, $amount, $deposit, $cancelWithdraw = false) {
        try {
            $matchingJavaAllow = env("MATCHING_JAVA_ALLOW", false);
            if ($matchingJavaAllow) {
                //send kafka ME Deposit
                $type = $deposit ?  1 : ($cancelWithdraw ? 3 : 2);
                $types = [1 => 'deposit', 2 => 'withdrawal', 3 => 'deposit'];
                $typeName = isset($types[$type]) ? $types[$type] : '';
                if ($amount < 0) {
                    $amount = BigNumber::new($amount)->mul(-1)->toString();
                }

                $payload = [
                    'type' => $typeName,
                    'data' => [
                        'userId' => $userId,
                        'coin' => $coin,
                        'amount' => $amount,
                        'transactionKrwId' => $transaction->id
                    ]
                ];

                if ($type == 3) {
                    $payload['data']['cancelWithdrawId'] = $transaction->id;
                }

                $command = SpotCommands::create([
                    'command_key' => md5(json_encode($payload)),
                    'type_name' => $typeName,
                    'user_id' => $userId,
                    'obj_id' => $transaction->id,
                    'payload' => json_encode($payload),

                ]);
                if (!$command) {
                    throw new HttpException(422, 'can not create command');
                }

                $payload['data']['commandId'] = $command->id;
                Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_COMMAND, $payload);

            }
        } catch (Exception $ex) {
            Log::error($ex);
            Log::error("++++++++++++++++++++ sendMEKRWDepositWithdraw: $userId, coin: $coin, amount: $amount (". ($deposit ? "deposit": "withdrawal") . ")");
        }
    }

    public function sendMETransferSpot($tranfer, $userId, $coin, $amount, $deposit) {
        try {
            $matchingJavaAllow = env("MATCHING_JAVA_ALLOW", false);
            if ($matchingJavaAllow) {
                //send kafka ME Deposit
                $type = $deposit ?  'deposit' : 'withdrawal';
                if ($amount < 0) {
                    $amount = BigNumber::new($amount)->mul(-1)->toString();
                }

                $payload = [
                    'type' => $type,
                    'data' => [
                        'userId' => $userId,
                        'coin' => $coin,
                        'amount' => $amount,
                        'tranferId' => $tranfer->id
                    ]
                ];

                $command = SpotCommands::create([
                    'command_key' => md5(json_encode($payload)),
                    'type_name' => $type,
                    'user_id' => $userId,
                    'obj_id' => $tranfer->id,
                    'payload' => json_encode($payload),

                ]);
                if (!$command) {
                    throw new HttpException(422, 'can not create command');
                }

                $payload['data']['commandId'] = $command->id;
                Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_COMMAND, $payload);

            }


        } catch (Exception $ex) {
            Log::error($ex);
            Log::error("++++++++++++++++++++ sendMETransferSpot: $userId, coin: $coin, amount: $amount (". ($deposit ? "deposit": "withdrawal") . ")");
        }

        if (env('SEND_BALANCE_LOG_TO_WALLET', false)) {
            if ($amount < 0) {
                $amount = BigNumber::new($amount)->mul(-1)->toString();
            }

            $typeTrans = $deposit ? 'TRANSFER_IN' : 'TRANSFER_OUT';
            SendBalanceLogToWallet::dispatch([
                'userId' => $userId,
                'walletType' => 'SPOT',
                'type' => $typeTrans,
                'currency' => $coin,
                'currencyAmount' => $amount,
                'currencyFeeAmount' => "0",
                'currencyAmountWithoutFee' => $amount,
                'date' => Utils::currentMilliseconds()
            ])->onQueue(Consts::QUEUE_BALANCE_WALLET);
        }
    }

    public function updateCollectStatus($address, $tx_hash, $currency)
    {
        if (Consts::CURRENCY_XRP === $currency || Consts::CURRENCY_TRX === $currency || Consts::CURRENCY_EOS === $currency) {
            $array_address_input = explode('|', $address);
            $toAddress = $array_address_input[0];
        } else {
            $toAddress = $address;
        }
        return Transaction::where('to_address', $toAddress)
            ->where('tx_hash', $tx_hash)
            ->update(['collect' => Consts::DEPOSIT_TRANSACTION_COLLECTED_STATUS]);
    }

    private function createUserTransaction($transaction)
    {
        if ($transaction->status == Consts::TRANSACTION_STATUS_SUCCESS) {
            $amount = BigNumber::new($transaction->amount)->sub($transaction->fee)->toString();
            UpdateUserTransaction::dispatchIfNeed(
                Consts::USER_TRANSACTION_TYPE_TRANSFER,
                $transaction->id,
                $transaction->user_id,
                $transaction->currency,
                $amount
            );
        }
    }

    private function notifyTransactionCreated($transaction)
    {
        $userId = $transaction->user_id;
        $currency = $transaction->currency;

        $user = User::find($transaction->user_id);

        if (
            $this->isDepositTransactionType($transaction) && $transaction->currency != Consts::CURRENCY_USD
            && $transaction->status === Consts::TRANSACTION_STATUS_SUCCESS
        ) {
            //$user->notify(new DepositAlerts($transaction, $transaction->currency));
        } elseif (
            $this->isWithdrawTransactionType($transaction) && $transaction->currency != Consts::CURRENCY_USD
            && $transaction->status === Consts::TRANSACTION_STATUS_SUCCESS
        ) {
            $user->notify(new WithdrawalVerifyAlert($transaction));
        }

        $this->fireEventBalanceChanged($userId, $currency);
        $this->fireEventTransactionCreated($userId, $transaction);
    }

    private function getUsdAmount($userBalance, $currency, $amount)
    {
        $amount = BigNumber::new($amount);
        if ($amount->isNegative()) { //withdrawing
            return $amount->mul($userBalance->usd_amount)->div($userBalance->balance)->toString();
        }
        return $this->priceService->toUsdAmount($currency, $amount->toString());
    }

    private function updateUserBalance(
        $userBalance,
        $currency,
        $transaction,
        $updateBalance = true,
        $updateAvailableBalance = true
    ) {
        $amount = BigNumber::new($transaction->amount)->sub($transaction->fee)->toString();

        $data = [];
        if ($updateBalance) {
            $data['balance'] = DB::raw('balance + ' . $amount);

            if ($currency !== 'usd') {
                $data['usd_amount'] = DB::raw('usd_amount + ' . $this->getUsdAmount($userBalance, $currency, $amount));
            }
        }

        if ($updateAvailableBalance) {
            $data['available_balance'] = DB::raw('available_balance + ' . $amount);
        }

        DB::table($this->getTableTypeDepositWithdraw($currency))
            ->where('id', $userBalance->id)
            ->update($data);
    }

    public function refundUserBalance(
        $userBalance,
        $currency,
        $transaction,
        $updateBalance,
        $updateAvailableBalance
    ): void {
        $amount = BigNumber::new($updateAvailableBalance)->add($transaction->fee)->toString();
        $data['available_balance'] = DB::raw('available_balance + ' . $amount);

        $isSpotMainBalance = env('DEPOSIT_WITHDRAW_SPOT_BALANCE', false);
        $table = $currency . '_accounts';
        if ($isSpotMainBalance) {
            $table = 'spot_' . $currency . '_accounts';
        }

        DB::table($table)
            ->where('id', $userBalance->id)
            ->update($data);
    }

    public function isWithdrawTransaction($currency, $transactionHash)
    {
        $transaction = Transaction::where('currency', $currency)
            ->where('transaction_id', $transactionHash)
            ->first();
        return $transaction && $transaction->amount < 0;
    }

    public function updateTransactionStatus($currency, $tmpWithdrawId, $tx_hash, $status)
    {
        $tmpWithdrawId = (string)$tmpWithdrawId;
        $tx_hash = (string)$tx_hash;
        logger(__FUNCTION__, compact('currency', 'tmpWithdrawId', 'tx_hash', 'status'));

        $transaction = Transaction::where('currency', $currency)
            ->where('tx_hash', $tmpWithdrawId)
            ->lockForUpdate()
            ->first();

        if (empty($transaction)) {
            return null;
            // throw new HttpException(422, 'Transaction not found');
        }

        if ($transaction->status !== Consts::TRANSACTION_STATUS_SUCCESS) {
            $transaction->status = $status;
            $transaction->tx_hash = $tx_hash;

            $transaction->save();
            $this->fireEventTransactionCreated($transaction->user_id, $transaction);
            $user = User::find($transaction->user_id);
            if ($transaction->status == Consts::TRANSACTION_STATUS_SUCCESS) {
				$user->notify(new WithdrawAlerts($transaction, $transaction->currency));
			} else {
				$this->sendWithdrawalCanceledNotification($transaction->user_id, $transaction);
			}

            logger(__FUNCTION__, ['msg' => 'Withdraw success']);

            return $transaction;
        }

        return false;
    }

    private function fireEventBalanceChanged($userId, $currency): void
    {
        SendBalance::dispatchIfNeed($userId, [$currency], Consts::TYPE_MAIN_BALANCE);
    }

    private function fireEventTransactionCreated($userId, $transaction): void
    {
        $event = new TransactionCreated($transaction, $userId);
        event($event);
    }

    public function sendWithdrawalCanceledNotification($userId, $transaction): void
    {
        $user = User::find($userId);
        $locale = $user->getLocale();
        $title = __('title.notification.cancel_withdraw',
            ['time' => Carbon::now()], $locale);
        $body = __('body.notification.cancel_withdraw',
            ['time' => Carbon::now()], $locale);
        FirebaseNotificationService::send($userId, $title, $body);
        $user->notify(new WithdrawalCanceledNotification($transaction));
    }

    public function getWithdrawDaily($currency, $userId): float|int
    {
        $amountWithdrawDaily = Transaction::where('user_id', $userId)
            ->where('currency', $currency)
            ->where('amount', '<', 0)
            ->where('status', '!=', Consts::TRANSACTION_STATUS_CANCEL)
            ->where('created_at', '>=', Carbon::today()->timestamp * 1000)
            ->sum('amount');
        return abs($amountWithdrawDaily);
    }

    public function getTransactionAddress($params)
    {
        $currency = $params['currency'];
        if ($currency === Consts::CURRENCY_USD) {
            return $params['foreign_bank_account'];
        } elseif ($currency === Consts::CURRENCY_XRP) {
            return $params['blockchain_address'] . Consts::XRP_TAG_SEPARATOR . $params['blockchain_sub_address'];
        } else {
            return $params['blockchain_address'];
        }
    }

    public function getAndLockUserBalance($currency, $userId)
    {
        return DB::table($this->getTableTypeDepositWithdraw($currency))
            ->where('id', $userId)
            ->lockForUpdate()
            ->first();
    }

    private function isUserBalanceEnough($userBalance, $amount, $withdrawLimit): bool
    {
        if (is_null($userBalance)) {
            return false;
        }

        // Check current available amount
        $availableBalance = $userBalance->available_balance;

        $deductAmount = BigNumber::new($amount)->sub($withdrawLimit->fee)->toString();

        return !(BigNumber::new(-1)->mul($deductAmount)->comp($availableBalance) > 0);
    }

    public function getWithdrawLimit($user, $currency)
    {
        // Check limitation and fee
        $withdrawLimits = MasterdataService::getOneTable('withdrawal_limits');
        $withdrawLimit = collect($withdrawLimits)->first(function ($value) use ($user, $currency) {
            return $value->security_level == $user->security_level
                && $value->currency == $currency;
        });
        return $withdrawLimit;
    }

    private function checkMinimumWithdrawal($amount, $withdrawLimit)
    {
        return (BigNumber::new(-1)->mul($amount)->comp($withdrawLimit->minium_withdrawal) < 0);
    }

    private function checkFeeWithdrawal($params, $withdrawLimit)
    {
        return BigNumber::new(-1)->mul($params['fee'])->add($withdrawLimit->fee)->toString() != 0;
    }

    private function checkLimitWithdrawal($params, $withdrawLimit)
    {
        return BigNumber::new(-1)->mul($params['limit'])->add($withdrawLimit->limit)->toString() != 0;
    }

    private function checkDailyLimitWithdrawal($params, $withdrawLimit)
    {
        return BigNumber::new(-1)->mul($params['daily_limit'])->add($withdrawLimit->daily_limit)->toString() != 0;
    }

    private function checkMinWithdrawal($params, $withdrawLimit)
    {
        return BigNumber::new(-1)->mul($params['minium_withdrawal'])->add($withdrawLimit->minium_withdrawal)->toString() != 0;
    }

    private function isWithdrawOverDailyLimit(User $user, $currency, $amount, $withdrawLimit)
    {
        $totalWithdrawDaily = $this->getWithdrawDaily($currency, $user->id);

        return (BigNumber::new(-1)->mul($amount)->add($totalWithdrawDaily)->comp($withdrawLimit->daily_limit) > 0);
    }

    /**
     * @param $params
     * @return mixed
     * @throws Exception
     */
    public function depositUsd($params): mixed
    {
        $coinsConfirmation = $this->getCoinsConfirmation('usd');
        if (!$coinsConfirmation->is_deposit) {
            throw new HttpException(422, __('messages.disable_coin_msg'));
        }

        // if ($user->security_level < Consts::SECURITY_LEVEL_BANK) {
        //     throw new HttpException(422, 'bank_account_unverified');
        // }
        if (BigNumber::new($params['amount'])->comp(0) <= 0) {
            throw new HttpException(422, Consts::DEPOSIT_ERROR_EXIST_AMOUNT_INVALID);
        }

        $adminBankAccount = AdminBankAccount::inRandomOrder()->first();
        $params['bank_name'] = $adminBankAccount->bank_name;
        $params['bank_branch'] = $adminBankAccount->bank_branch;
        $params['account_name'] = $adminBankAccount->account_name;
        $params['account_no'] = $adminBankAccount->account_no;
        $params['code'] = Utils::generateRandomString(10,
            '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');

        $transaction = UsdTransaction::create($params);

        $this->fireEventTransactionCreated($transaction->user_id, $transaction);
        return $transaction;
    }

    private function sendNewTransactionNotificationToAdmin($transaction)
    {
        $user = User::find($transaction->user_id);
        $user->notify(new AdminUsdAlerts($transaction));

        Notification::send(Admin::all(), new NotificationTransactionCreated($transaction));
    }

    private function isWithdrawTransactionType($transaction)
    {
        $amount = BigNumber::new($transaction->amount)->sub($transaction->fee)->toString();
        return !!(BigNumber::new($amount)->comp(0) < 0);
    }

    private function isDepositTransactionType($transaction): bool
    {
        return !$this->isWithdrawTransactionType($transaction);
    }

    public function getUsdTransactionHistory($params)
    {
        $limit = Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        $type = Arr::get($params, 'type');

        $query = UsdTransaction::query()
            ->where('user_id', Auth::id())
            ->select([
                'id',
                'user_id',
                DB::raw('ABS(amount) as amount'),
                'fee',
                'bank_name',
                'bank_branch',
                'account_name',
                'account_no',
                'code',
                'created_at',
                'updated_at',
                'status'
            ]);
        $query->when(!empty($type), function ($q) use ($type) {
            $conditionAmount = $type === Consts::TRANSACTION_TYPE_DEPOSIT ? '>' : '<';
            $q->where('amount', $conditionAmount, 0);
        })->when(!empty($params['sort']), function ($q) use ($params) {
            $q->orderBy($params['sort'], Arr::get($params, 'sort_type', 'desc'));
        }, function ($q) {
            $q->orderBy('created_at', 'desc');
        });

        return $query->paginate($limit);
    }

    public function cancelUsdDepositTransaction($transactionId): bool
    {
        $transaction = UsdTransaction::where('user_id', Auth::id())
            ->where('id', $transactionId)
            ->filterDeposit()
            ->where('status', Consts::TRANSACTION_STATUS_PENDING)
            ->lockForUpdate()
            ->first();

        if (!$transaction) {
            //throw new HttpException(422, 'Invalid transaction');
            return false;
        }

        $transaction->status = Consts::TRANSACTION_STATUS_CANCEL;
        $transaction->save();

        $this->fireEventTransactionCreated($transaction->user_id, $transaction);
        return true;
    }

    public static function getReferredFee($userIds, $startDate, $endDate)
    {
        $coins = MasterdataService::getCurrenciesAndCoins();
        $selectStatements = [];
        foreach ($coins as $coin) {
            $selectStatements[] = "SUM(IF(currency='{$coin}',fee,0)) as {$coin}_fee";
        }
        return Transaction::select(DB::raw(implode(",", $selectStatements)))
            ->addSelect('user_id')
            ->whereIn('user_id', $userIds)
            ->where('transactions.status', Consts::TRANSACTION_STATUS_SUCCESS)
            ->where('fee', '>', 0)
            ->whereBetween('transaction_date', array($startDate, $endDate))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');
    }

    public function getFee($params): \Illuminate\Support\Collection
    {
        $startDate = $params['start_date'];
        $endDate = $params['end_date'];
        $limit = $params['limit'];
        $sort = Arr::get($params, 'sort', 'transaction_date');
        $sortType = Arr::get($params, 'sort_type', 'desc');

        $feesGroupByDate = $this->buildGetFeeQuery($startDate, $endDate)
            ->addSelect('transaction_date')
            ->orderBy($sort, $sortType)
            ->groupBy('transaction_date')
            ->paginate($limit);
        return collect([
            'total_fee' => $this->buildGetFeeQuery($startDate, $endDate)->first()
        ])->merge($feesGroupByDate);
    }

    public function getTotalFee($params)
    {
        $startDate = $params['start_date'];
        $endDate = $params['end_date'];

        return $this->buildGetFeeQuery($startDate, $endDate)->first();
    }

    private function buildGetFeeQuery($startDate, $endDate)
    {
        $coins = MasterdataService::getCurrenciesAndCoins();
        $selectStatements = [];
        foreach ($coins as $coin) {
            $selectStatements[] = "SUM(IF(currency='{$coin}',fee,0)) as {$coin}_fee";
        }
        return Transaction::select(DB::raw(implode(",", $selectStatements)))
            ->where('status', Consts::TRANSACTION_STATUS_SUCCESS)
            ->where('fee', '>', 0)
            ->whereBetween('transaction_date', array($startDate, $endDate));
    }

    public function exportExcelProfitAndLoss($params): array
    {
        $transactions = $this->getUserTransactions($params);
        $rows = [];
        $totalDebit = "0";
        $totalCredit = "0";
        $totalEndingBalance = "0";
        $totalStartingBalance = "0";
        $totalIncreaseBalance = "0";
        foreach ($transactions as $transaction) {
            $debit = $transaction->debit;
            $credit = $transaction->credit;
            $endingBalance = $transaction->ending_balance;
            $startingBalance = $this->getStartingBalance($transaction);
            $increaseBalance = $this->getIncreaseBalance($transaction);
            $totalDebit = (new BigNumber($totalDebit))->add(BigNumber::round($this->priceService->toUsdAmount($transaction->currency,
                $debit), 'half_up', 0))->toString();
            $totalCredit = (new BigNumber($totalCredit))->add(BigNumber::round($this->priceService->toUsdAmount($transaction->currency,
                $credit), 'half_up', 0))->toString();
            $totalEndingBalance = (new BigNumber($totalEndingBalance))->add(BigNumber::round($this->priceService->toUsdAmount($transaction->currency,
                $endingBalance), 'half_up', 0))->toString();
            $totalStartingBalance = (new BigNumber($totalStartingBalance))->add(BigNumber::round($this->priceService->toUsdAmount($transaction->currency,
                $startingBalance), 'half_up', 0))->toString();
            $totalIncreaseBalance = (new BigNumber($totalIncreaseBalance))->add(BigNumber::round($this->priceService->toUsdAmount($transaction->currency,
                $increaseBalance), 'half_up', 0))->toString();
            $temp = [
                strtoupper($transaction->currency),
                $this->trimValueBigNumber(BigNumber::round($startingBalance, 'half_up', 10)),
                $this->trimValueBigNumber(BigNumber::round($debit, 'half_up', 10)),
                $this->trimValueBigNumber(BigNumber::round($credit, 'half_up', 10)),
                $this->trimValueBigNumber(BigNumber::round($endingBalance, 'half_up', 10)),
                $this->trimValueBigNumber(BigNumber::round($increaseBalance, 'half_up', 10)),
                $this->getPercentIncreaseBalance($increaseBalance, $startingBalance)
            ];
            array_push($rows, $temp);
        }
        $totalPercentIncreaseBalance = $this->getPercentIncreaseBalance($totalIncreaseBalance, $totalStartingBalance);
        $sum = [
            __('Sum'),
            BigNumber::round($totalStartingBalance, 'half_up', 0),
            BigNumber::round($totalDebit, 'half_up', 0),
            BigNumber::round($totalCredit, 'half_up', 0),
            BigNumber::round($totalEndingBalance, 'half_up', 0),
            BigNumber::round($totalIncreaseBalance, 'half_up', 0),
            $totalPercentIncreaseBalance
        ];
        if (count($rows) > 0) {
            array_unshift($rows, $sum);
        }
        array_unshift($rows,
            [__('Account'), __('Opening'), __('Debit'), __('Credit'), __('Ending'), __('Change'), __('Yeild')]);
        return $rows;
    }

    private function getStartingBalance($transaction)
    {
        return $transaction->ending_balance + $transaction->credit - $transaction->debit;
    }

    private function getIncreaseBalance($transaction)
    {
        return $transaction->ending_balance - $this->getStartingBalance($transaction);
    }

    private function getPercentIncreaseBalance($increase, $startBalance): string
    {
        $increase = new BigNumber($increase);
        $startBalance = new BigNumber($startBalance);
        if ($increase->toString() === "0") {
            return "0%";
        }
        if ($startBalance->toString() === "0") {
            return "100%";
        }
        return round(floatval($increase->div($startBalance)->mul(100)->toString()), 2) . '%';
    }

    private function trimValueBigNumber($val)
    {
        return strpos($val, '.') !== false ? rtrim(rtrim($val, '0'), '.') : $val;
    }

    public function getTransactions($params)
    {   
        $transactions = Transaction::leftJoin('users', function ($join) {
            $join->on('transactions.user_id', '=', 'users.id');
        })
            ->leftJoin('networks', function ($join) {
                $join->on('transactions.network_id', '=', 'networks.id');
            })
            ->when(!empty($params['user_id']), function ($query) use ($params) {
                return $query->where('transactions.user_id', $params['user_id']);
            })
            ->when(!empty($params['start_date']), function ($query) use ($params) {
                $startDate = Carbon::createFromTimestamp($params['start_date']);
             
                return $query->where('transactions.created_at', '>=', $startDate->timestamp * 1000);
            })
            ->when(!empty($params['end_date']), function ($query) use ($params) {
                $endDate = Carbon::createFromTimestamp($params['end_date']) ;
               
                return $query->where('transactions.created_at', '<', $endDate->timestamp * 1000);
            })
            ->when(!empty($params['search_key']), function ($query) use ($params) {
                $searchKey = Arr::get($params, 'search_key');
                return $query->where(function ($q) use ($searchKey) {
                    $q->where('transactions.transaction_id', 'like', '%' . $searchKey . '%')
                        ->orWhere('transactions.tx_hash', 'like', '%' . $searchKey . '%')
                        ->orWhere('transactions.user_id', 'like', '%' . $searchKey . '%')
                        ->orWhere('transactions.blockchain_address', 'like', '%' . $searchKey . '%')
                        ->orWhere('transactions.currency', 'like', '%' . $searchKey . '%')
                        ->orWhere('transactions.status', 'like', '%' . $searchKey . '%')
                        ->orWhere('users.email', 'like', '%' . $searchKey . '%')
                        ->orWhere('networks.name', 'like', '%' . $searchKey . '%');
                });
            })
            ->when(!empty($params['currency']), function ($query) use ($params) {
                return $query->where('transactions.currency', $params['currency']);
            })
            ->when(!empty($params['collect']), function ($query) use ($params) {
                return $query->where('transactions.collect', $params['collect']);
            })
            ->when(!empty($params['type']), function ($query) use ($params) {
                switch ($params['type']) {
                    case Consts::TRANSACTION_TYPE_DEPOSIT:
                        return $query->filterDeposit();
                    case Consts::TRANSACTION_TYPE_WITHDRAW:
                        return $query->filterWithdraw();
                    default:
                        break;
                }
            })
            ->when(!empty($params['status']), function ($query) use ($params) {
                $status = [Consts::TRANSACTION_STATUS_PENDING, Consts::TRANSACTION_STATUS_SUMITTED];
                if ($params['status'] !== Consts::TRANSACTION_STATUS_PENDING) {
                    $status = [
                        Consts::TRANSACTION_STATUS_SUCCESS,
                        Consts::TRANSACTION_STATUS_ERROR,
                        Consts::TRANSACTION_STATUS_CANCEL,
                        Consts::TRANSACTION_STATUS_REJECTED
                    ];
                }
                return $query->whereIn('transactions.status', $status);
            })
            ->when(
                !empty($params['sort']) && !empty($params['sort_type']),
                function ($query) use ($params) {
                    return $query->orderBy($params['sort'], $params['sort_type']);
                },
                function ($query) use ($params) {
                    return $query->orderBy('transactions.created_at', 'desc');
                }
            )
            ->select(
                'transactions.id',
                'users.email',
                'transactions.transaction_id',
                'transactions.currency',
                'transactions.is_external',
                'transactions.tx_hash',
                'transactions.amount',
                'transactions.fee',
                'transactions.status',
                'transactions.created_at',
                'transactions.approved_by',
                'transactions.collect',
                'transactions.network_id',
                'networks.name as network_name'
            )
            ->paginate(Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE));

        return $transactions;
    }

    public function getTotalPendingWithdraw($params)
    {
        $query = Transaction::select(DB::raw('(ABS(SUM(amount)) + SUM(fee)) as total'))
            ->where('user_id', Auth::id())
            ->filterWithdraw()
            ->whereIn('status', [Consts::TRANSACTION_STATUS_PENDING, Consts::TRANSACTION_STATUS_ERROR]);
        if (!empty($params['currency'])) {
            return $query->where('currency', $params['currency'])->first();
        }
        return $query->addSelect('currency')->groupBy('currency')->get();
    }

    public function getTotalUsdPendingWithdraw()
    {
        return UsdTransaction::select(DB::raw('(ABS(SUM(amount)) + SUM(fee)) as total'))
            ->where('user_id', Auth::id())
            ->filterWithdraw()
            ->whereIn('status', [Consts::TRANSACTION_STATUS_PENDING, Consts::TRANSACTION_STATUS_ERROR])
            ->first();
    }

    public function getCoinsConfirmation($coin)
    {
        return CoinsConfirmation::select('is_withdraw', 'is_deposit')
            ->where('coin', '=', $coin)
            ->first();
    }
}
