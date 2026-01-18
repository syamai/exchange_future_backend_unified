<?php

namespace App\Jobs;

use App\Consts;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Exception;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendBonusFailAlertToAdminMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Http\Services\AirdropService;
use App\Models\Airdrop\AutoDividendHistory;
use App\Models\Airdrop\ManualDividendHistory;
use App\Models\TotalBonusEachPair;
use Transaction\Models\Transaction;
use App\Utils;
use Illuminate\Queue\FailingJob;
use App\Http\Services\UserService;
use App\Events\BalanceUpdated;

class SendBonusByAdminJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    private $data;
    private $payfor;
    private $transactionId;
    public $tries = Consts::UNLOCK_ATTEMPTS;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data, $payfor, $transactionId = null)
    {
        $this->data = $data;
        $this->payfor = $payfor;
        $this->transactionId = $transactionId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DB::beginTransaction();
        try {
            $this->sendBonusByAdmin($this->data, $this->payfor);
            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    public function sendBonusByAdmin($data, $payfor)
    {
        $user = User::find($data['user_id']);
        if (!$user->isActive()) {
            $email = DB::table('settings')->where('key', Consts::SETTING_CONTACT_EMAIL)->pluck('value');
            Mail::queue(new SendBonusFailAlertToAdminMail($email, $user->email, $user->id, $data['amount'], $data['currency'], $payfor));
            return;
        }

        $transferSucess = $this->transferBalance($data, $payfor);
        if ($transferSucess) {
            $this->updateHistoryStatus($data, $this->transactionId);
        }
        app(AirdropService::class)->updateTotalBonus($data['amount'], $data['currency']);
        if (array_key_exists('instrument_symbol', $data)) {
            TotalBonusEachPair::where('coin', strtoupper($data['instrument_symbol']))
                    ->where('payout_coin', $data['currency'])
                    ->update([
                        'total_paid' => DB::raw('total_paid + ' . $data['amount'])
                    ]);
        } else {
            app(AirdropService::class)->updateTotalBonusInPair($data['amount'], $data['currency'], $data['trading_coin'], $data['trading_currency']);
        }

        $this->createDepositTransaction();
        $balances = app(UserService::class)->getUserAccounts($data['user_id']);
        event(new BalanceUpdated($data['user_id'], $balances));
    }

    public function updateHistoryStatus($data, $transactionId)
    {
        if ($transactionId) {
            return AutoDividendHistory::where('user_id', $data['user_id'])
                ->where('transaction_id', $transactionId)
                ->update([
                    'status' => Consts::TRANSACTION_STATUS_SUCCESS
                ]);
        }
        return ManualDividendHistory::where('user_id', $data['user_id'])
            ->where('filter_from', $data['filter_from'])
            ->where('filter_to', $data['filter_to'])
            ->update([
                'status' => Consts::TRANSACTION_STATUS_SUCCESS
            ]);
    }

    public function transferBalance($data, $payfor): int
    {
        $data = (object) $data;
        $userId = $data->user_id;
        $currency = $data->currency;
        if ($payfor == Consts::TYPE_AIRDROP_BALANCE || $payfor == Consts::TYPE_DIVIDEND_BONUS_BALANCE) {
            $table = 'airdrop_' . $currency.'_accounts';
        } else {
            $table = $currency.'_accounts';
        }

        if ($payfor == Consts::TYPE_AIRDROP_BALANCE) {
            return DB::table($table)
            ->where('id', $userId)
            ->update([
                'balance' => DB::raw('balance + ' . $data->amount),
            ]);
        } elseif ($payfor == Consts::TYPE_DIVIDEND_BONUS_BALANCE) {
            return DB::table($table)
            ->where('id', $userId)
            ->update([
                'balance_bonus' => DB::raw('balance_bonus + ' . $data->amount),
            ]);
        } else {
            return DB::table($table)
            ->where('id', $userId)
            ->update([
                'balance' => DB::raw('balance + ' . $data->amount),
                'available_balance' => DB::raw('available_balance + ' . $data->amount)
            ]);
        }
    }

    private function createDepositTransaction(): Transaction
    {
        $this->data = (object) $this->data;
        $transaction = new Transaction();
        $data = [
            'transaction_id' => Amanpuri_unique(),
            'user_id' => $this->data->user_id,
            'currency' => $this->data->currency,
            'tx_hash' => '',
            'amount' => $this->data->amount,
            'from_address' => Consts::PAYBONUSTRADING,
            'to_address' => $this->getUserAddress($this->data->user_id, $this->data->currency),
            'blockchain_sub_address' => "",
            'fee' => 0,
            'transaction_date' => Carbon::now(),
            'status' => Consts::TRANSACTION_STATUS_SUCCESS,
            'type' => Consts::TRANSACTION_TYPE_DEPOSIT,
            'collect' => Consts::DEPOSIT_TRANSACTION_COLLECTED_STATUS,
            'created_at' => Utils::dateTimeToMilliseconds(Carbon::now()),
            'updated_at' => Utils::currentMilliseconds(),
        ];
        $transaction->fill($data);
        $transaction->save();
        return $transaction;
    }

    public function getUserAddress($userId, $currency)
    {
        return DB::table($currency . '_accounts')->where('id', $userId)->value('blockchain_address');
    }

    public function fail($exception = null)
    {
        if ($this->job && $this->transactionId) {
            // @phpstan-ignore-next-line
            FailingJob::handle($this->job->getConnectionName(), $this->job, $exception);
            AutoDividendHistory::where('user_id', $this->data['user_id'])
            ->where('transaction_id', $this->transactionId)
            ->update([
                'status' => Consts::AIRDROP_FAIL
            ]);

            //Send mail to admin
        }
    }
}
