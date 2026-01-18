<?php
/**
 * Created by PhpStorm.
 * Date: 5/2/19
 * Time: 3:43 PM
 */

namespace Transaction\Http\Services;

use App\Consts;
use App\Events\TransactionCreated;
use App\Events\WithdrawDepositBalanceEvent;
use App\Http\Services\FirebaseNotificationService;
use App\Http\Services\HotWalletService;
use App\Http\Services\MasterdataService;
use App\Jobs\SendBalance;
use App\Jobs\UpdateUserTransaction;

use App\Notifications\DepositAlerts;
use App\Notifications\WithdrawAlerts;
use App\Models\User;
use Carbon\Carbon;
use Transaction\Models\Transaction;
use App\Utils;
use App\Utils\BigNumber;

/**
 * Class TransactionService
 * @package Transaction\Http\Services
 */
class TransactionService
{
    /**
     * @var WalletService
     */
    protected $walletService;

    /**
     * TransactionService constructor.
     */
    public function __construct()
    {
        $this->walletService = new WalletService();
    }

    /**
     * @param array $params
     * @return mixed
     */
    public function getExternalWithdrawTransaction($params = [])
    {
        $limit = \Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);

        return Transaction::join('users', 'users.id', 'transactions.user_id')
            ->leftJoin('admins as approval_person', 'approval_person.id', 'transactions.approved_by')
            ->filterExternalWithdraw($params)
            ->select(
                'transactions.*',
                'users.email as sender',
                'approval_person.email as withdraw_approval'
            )
            ->paginate($limit);
    }

    /**
     * @param $transactionId
     * @return mixed
     */
    public function getWithdrawTransaction($transactionId)
    {
        return Transaction::where('transaction_id', $transactionId)->filterWithdraw()->first();
    }

    /**
     * @param $transactionId
     * @return mixed
     */
    public function getWithdrawTransactionById($transactionId)
    {
        return Transaction::where('transaction_id', $transactionId)
            ->filterWithdraw()
            ->first();
    }

    /**
     * @param $params
     * @return mixed
     */
    public function getWithdrawalHistory($params)
    {
        $limit = \Arr::get($params, 'limit', Consts::DEFAULT_PER_PAGE);
        $sort = \Arr::get($params, 'sort');
        $sortType = \Arr::get($params, 'sort_type');
        $keySearch = \Arr::get($params, 'key_search');
        $startDate = \Arr::get($params, 'startDate');
        $endDate = \Arr::get($params, 'endDate');

        return Transaction::join('users', 'user_id', 'users.id')
            ->filterWithdraw()
            ->when(!is_null($keySearch), function ($query) use ($keySearch) {
                $query->where(function ($subQuery) use ($keySearch) {
                    // $keySearch = Utils::escapeLike($keySearch);
                    $subQuery->orWhere('users.email', 'like', "%$keySearch%")
                        ->orWhere('transactions.transaction_id', 'like', "%$keySearch%")
                        ->orWhere('transactions.to_address', 'like', "%$keySearch%")
                        ->orWhere('transactions.tx_hash', 'like', "%$keySearch%")
                        ->orWhere('transactions.status', 'like', "%$keySearch%");
                });
            })
            ->when($sort, function ($query) use ($sort, $sortType) {
                $query->orderBy($sort, $sortType ?? 'desc')
                    ->orderBy('transactions.created_at', $sortType ?? 'desc');
            }, function ($query) {
                $query->orderBy('transactions.created_at', 'desc')
                    ->orderBy('users.email', 'desc');
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('transaction_date', [$startDate, $endDate]);
            })
            ->select('transactions.*', 'email')
            ->paginate($limit);
    }


    /**
     * @param $userBalance
     * @param $amount
     * @return bool
     */
    public function isUserBalanceEnough($userBalance, $amount)
    {
        if (is_null($userBalance)) {
            return false;
        }
        // Check current available amount
        $availableBalance = $userBalance->available_balance;
        return !(BigNumber::new(-1)->mul($amount)->comp($availableBalance) > 0);
    }

    /**
     * @param $currency
     * @param $userId
     * @return float|int
     */
    public function getWithdrawVolume($currency, $userId)
    {
        $startTime = Utils::getWithdrawalLimitStartOfTimeInMillis($currency);
        $endTime = Utils::getLimitEndOfTimeInMillis();

        $amountWithdrawVolume = Transaction::where('user_id', $userId)
            ->where('currency', $currency)
            ->whereBetween('created_at', [$startTime, $endTime])
            ->filterWithdraw()
            ->whereNotIn('status', [
                Transaction::REJECTED_STATUS,
                Transaction::CANCELED_STATUS
            ])
            ->sum('amount');
        return abs($amountWithdrawVolume);
    }

    /**
     * @param $currency
     * @return mixed
     */
    public function getWithdrawLimit($currency)
    {
        // Check limitation and fee
        $withdrawLimits = MasterdataService::getOneTable('withdrawal_limits');

        $userSecurityLevel = auth('api')->user()->security_level;

        $withdrawLimit = collect($withdrawLimits)->first(function ($value) use ($currency, $userSecurityLevel) {
            return $value->security_level === $userSecurityLevel && $value->currency == $currency;
        });

        return $withdrawLimit;
    }

    /**
     * @param $user_id
     * @param $currency
     * @param $amount
     * @param $withdrawLimit
     * @return bool
     */
    public function isWithdrawOverLimit($user_id, $currency, $amount, $withdrawLimit)
    {
        $withdrawalVolume = $this->getWithdrawVolume($currency, $user_id);
        $amount = BigNumber::new(-1)->mul($amount)->toString();

        return BigNumber::new($withdrawLimit->limit)
                ->sub($withdrawalVolume)
                ->sub($amount)->comp(0) < 0;
    }

    /**
     * @param $transaction
     */
    public function createUserTransaction($transaction)
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

    /**
     * @param $transaction
     */
    public function notifyTransactionCreated($transaction)
    {
        $userId = $transaction->user_id;
        $currency = $transaction->currency;

        $user = User::find($transaction->user_id);

        if ($this->isDepositTransactionType($transaction) && $transaction->currency != Consts::CURRENCY_USD
            && $transaction->status === Consts::TRANSACTION_STATUS_SUCCESS) {
            $locale = $user->getLocale();
            $title = __('title.notification.deposit_success', [], $locale);
            $body = __('body.notification.deposit_success', ['time' => Carbon::now()], $locale);
            FirebaseNotificationService::send($user->id, $title, $body);
            $user->notify(new DepositAlerts($transaction, $transaction->currency));
        } elseif ($this->isWithdrawTransactionType($transaction) && $transaction->currency != Consts::CURRENCY_USD
            && $transaction->status === Consts::TRANSACTION_STATUS_SUCCESS) {
            $locale = $user->getLocale();
            $title = __('title.notification.withdraw_success', [], $locale);
            $body = __('body.notification.withdraw_success', ['time' => Carbon::now()], $locale);
            $user->notify(new WithdrawAlerts($transaction, $transaction->currency));
            FirebaseNotificationService::send($user->id, $title, $body);
        }

        $this->fireEventBalanceChanged($userId, $currency);
        $this->fireEventTransactionCreated($userId, $transaction);
    }

    /**
     * @param $transaction
     * @return bool
     */
    public function isDepositTransactionType($transaction)
    {
        return !$this->isWithdrawTransactionType($transaction);
    }

    /**
     * @param $transaction
     * @return bool
     */
    private function isWithdrawTransactionType($transaction)
    {
        $amount = BigNumber::new($transaction->amount)->sub($transaction->fee)->toString();
        return !!(BigNumber::new($amount)->comp(0) < 0);
    }

    /**
     * @param $userId
     * @param $currency
     */
    private function fireEventBalanceChanged($userId, $currency)
    {
        $store = Consts::TYPE_MAIN_BALANCE;
        SendBalance::dispatchIfNeed($userId, [$currency], $store);
        $this->transactionBalanceEvent($currency, $userId);
    }

    public function transactionBalanceEvent($currency, $userId)
    {
        $service = new UserBalanceService();
        $data = $service->getBalanceTransactionMain($currency, $userId);

        event(new WithdrawDepositBalanceEvent($userId, $data));
    }

    /**
     * @param $userId
     * @param $transaction
     */
    private function fireEventTransactionCreated($userId, $transaction)
    {
        $event = new TransactionCreated($transaction, $userId);
        event($event);
    }

    public function signTransaction($params)
    {
        $hotWalletService = new HotWalletService();
        return $hotWalletService->signTransaction(($params));
    }

    public function notifyWithdrawVerify($transaction, $depositTransaction)
    {
        $this->createUserTransaction($transaction);
        $this->createUserTransaction($depositTransaction);
        $this->notifyTransactionCreated($transaction);
        $this->notifyTransactionCreated($depositTransaction);
    }

    public function sendMEDepositWithdraw($transaction, $userId, $coin, $amount, $deposit, $cancelWithdraw) {
        $transactionService = app(\App\Http\Services\TransactionService::class);
        $transactionService->sendMEDepositWithdraw($transaction, $userId, $coin, $amount, $deposit, $cancelWithdraw);
    }
}
