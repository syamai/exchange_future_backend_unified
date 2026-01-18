<?php

namespace App\Jobs;

use App\Consts;
use Carbon\Carbon;
use App\Models\AirdropHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Utils\BigNumber;
use Exception;
use Illuminate\Support\Facades\Mail;
use App\Mail\UnlockAirdropFailAlertToAdminMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Http\Services\AirdropDepositService;
use App\Models\AirdropSetting;
use Transaction\Models\Transaction;
use App\Utils;

class SendAirdropBalanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    private $record;
    public $tries = Consts::UNLOCK_ATTEMPTS;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($record)
    {
        $this->record = $record;
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
            $this->sendAirdropBalance($this->record);
            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    public function sendAirdropBalance($record)
    {
        $this->transferBalanceToMain($record);
        $this->createDepositTransaction();
        $this->updateSuccessRecord($record->id);
        $this->updateRemainAndTotalPaidAmount($record);
    }

    public function updateSuccessRecord($recordId)
    {
        return AirdropHistory::where('id', $recordId)->update([
            'status' => Consts::AIRDROP_PAID,
            'updated_at' => Carbon::now()
        ]);
    }

    public function updateRemainAndTotalPaidAmount($record)
    {
        $setting = AirdropSetting::where('status', Consts::AIRDROP_SETTING_ACTIVE)
            ->lockForUpdate()
            ->first();
        $remaining = $setting->remaining;
        $totalHasPaid = $setting->total_paid;
        AirdropSetting::where('status', Consts::AIRDROP_SETTING_ACTIVE)
            ->update([
                'remaining' => BigNumber::new($remaining)->sub($record->amount),
                'total_paid' => BigNumber::new($totalHasPaid)->add($record->amount),
            ]);
        // TODO: Refactor use function UpdateAirdropSetting of AirdropService
        $setting = AirdropSetting::where('status', Consts::AIRDROP_SETTING_ACTIVE)->first();
        cache(['airdrop:setting:current' => $setting], config('airdrop.airdrop_setting_live_time_cache'));
    }

    public function depositTransaction($record)
    {
        $user = User::where('id', $record->user_id)->first();
        $amount = $record->amount;
        $currency = $record->currency;
        $airdropDepositService = new AirdropDepositService($user, $amount, $currency);
        $airdropDepositService->deposit();
    }

    public function transferBalanceToMain($record)
    {

        $userId = $record->user_id;
        $currency = $record->currency;
        $table = $currency.'_accounts';
        $rs = DB::connection('master')
                ->table($table)
                ->where('id', $userId)
                ->lockForUpdate()
                ->first();
        if ($rs) {
            DB::table($table)
                ->where('id', $userId)
                ->update([
                    'balance' => BigNumber::new($rs->balance)->add($record->amount)->toString(),
                    'available_balance' => BigNumber::new($rs->available_balance)->add($record->amount)->toString()
                ]);
        }
    }

    private function createDepositTransaction(): Transaction
    {
        $transaction = new Transaction();
        $data = [
            'transaction_id' => Amanpuri_unique(),
            'user_id' => $this->record->user_id,
            'currency' => $this->record->currency,
            'tx_hash' => '',
            'amount' => $this->record->amount,
            'from_address' => Consts::DIVIDEND,
            'to_address' => $this->getUserAddress($this->record->user_id, $this->record->currency),
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
        if ($this->job) {
            // @phpstan-ignore-next-line
            FailingJob::handle($this->job->getConnectionName(), $this->job, $exception);
            AirdropHistory::where('id', $this->record->id)->update([
                'status' => Consts::AIRDROP_UNPAID,
                'updated_at' => Carbon::now()
            ]);

            //Send mail to user and admin

            // @phpstan-ignore-next-line
            $record = AirdropHistory::where('id', $this->recordId)->first();
            // @phpstan-ignore-next-line
            $amount = BigNumber::new($this->unlockPercent)->mul($record->total_balance)->div(100)->toString();
            // Mail::queue( new UnlockAirdropFailMail($amount, $record->email, $record->user_id));

            $email = DB::table('settings')->where('key', Consts::SETTING_CONTACT_EMAIL)->pluck('value');
            Mail::queue(new UnlockAirdropFailAlertToAdminMail($email, $record->email, $record->user_id, $amount));
        }
    }
}
