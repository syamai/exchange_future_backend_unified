<?php

namespace App\Jobs;

use App\Consts;
use Carbon\Carbon;
use App\Models\AirdropHistoryLockBalance;
use App\Models\AirdropAmalAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use App\Utils\BigNumber;
use Exception;
use Illuminate\Support\Facades\Mail;
use App\Mail\UnlockAirdropFailAlertToAdminMail;
use Illuminate\Support\Facades\DB;
use App\Models\AmalAccount;
use App\Http\Services\UserService;
use App\Events\BalanceUpdated;

class UnlockBalanceAirdrop implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    private $recordId;
    private $unlockPercent;
    public $tries = Consts::UNLOCK_ATTEMPTS;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($recordId, $unlockPercent)
    {
        $this->recordId = $recordId;
        $this->unlockPercent = $unlockPercent;
    }

    public function fail($exception = null)
    {
        if ($this->job) {
            // @phpstan-ignore-next-line
            FailingJob::handle($this->job->getConnectionName(), $this->job, $exception);
            AirdropHistoryLockBalance::where('id', $this->recordId)->update([
                'status' => Consts::AIRDROP_FAIL,
                'updated_at' => Carbon::now()
            ]);
            //Send mail to user and admin
            $record = AirdropHistoryLockBalance::where('id', $this->recordId)->first();
            $amount = BigNumber::new($this->unlockPercent)->mul($record->total_balance)->div(100)->toString();
            // Mail::queue( new UnlockAirdropFailMail($amount, $record->email, $record->user_id));

            $email = DB::table('settings')->where('key', Consts::SETTING_CONTACT_EMAIL)->pluck('value');
            Mail::queue(new UnlockAirdropFailAlertToAdminMail($email, $record->email, $record->user_id, $amount));
        }
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
            $this->calculateUnlockBalance($this->recordId, $this->unlockPercent);
            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            logger()->error($exception);
            Log::error($exception);
            throw $exception;
        }
    }

    public function calculateUnlockBalance($recordId, $unlockPercent)
    {
        $percisionRound = config('airdrop.percision_round');
        $record = AirdropHistoryLockBalance::where('id', $recordId)->first();
        $amount = BigNumber::new($unlockPercent)->mul($record->total_balance)->div(100)->toString();
        $totalBalance = BigNumber::new($record->total_balance)->sub($percisionRound)->toString();
        $unlockedBalance = BigNumber::new($amount)->add($record->unlocked_balance)->toString();

        if ($unlockedBalance < $totalBalance) {
            $this->updateUnlockBalanceAccount($record->user_id, $amount, $record->type);

            return AirdropHistoryLockBalance::where('id', $recordId)->update([
                'status' => Consts::AIRDROP_UNLOCKING,
                'amount' => $amount,
                'unlocked_balance' => $unlockedBalance,
                'last_unlocked_date' => Carbon::now()->toDateString(),
                'updated_at' => Carbon::now()
            ]);
        } else {
            $amount = BigNumber::new($record->total_balance)->sub($record->unlocked_balance)->toString();
            $this->updateUnlockBalanceAccount($record->user_id, $amount, $record->type);

            return AirdropHistoryLockBalance::where('id', $recordId)->update([
                'status' => Consts::AIRDROP_SUCCESS,
                'amount' => $amount,
                'unlocked_balance' => $record->total_balance,
                'last_unlocked_date' => Carbon::now()->toDateString(),
                'updated_at' => Carbon::now()
            ]);
        }
    }

    public function updateUnlockBalanceAccount($userId, $amount, $type)
    {
        // in case system send bonus dividend auto or manually to users
        if ($type == Consts::AIRDROP_TYPE_ADMIN) {
            $result = AirdropAmalAccount::where('id', $userId)
                ->update([
                    'available_balance_bonus' => DB::raw('available_balance_bonus + ' . $amount),
                    'last_unlock_date' => Carbon::now()->toDateString()
                ]);
            $balances = app(UserService::class)->getUserAccounts($userId);
            event(new BalanceUpdated($userId, $balances));
            return $result;
        }

        // in case users have been transferred from their main balance to dividend wallet
        // here we'll return a part of amount back to their main balance

        //update 01/07/2020 : in case type = '' or type = special, we will tranfer amount to main balance instead of airdrop_amal_account
        // in the last update, we only setting type=special for this case. Now, we add type = "" for this case.

        // if($type == Consts::AIRDROP_TYPE_SPECIAL) {
        $updateLastUnlock = AirdropAmalAccount::where('id', $userId)
        ->update([
            'balance' => DB::raw("balance - " . $amount),
            'last_unlock_date' => Carbon::now()->toDateString()
        ]);

        $amalAccount = AmalAccount::lockForUpdate()->where('id', $userId)->first();
        if (!$amalAccount) {
            return false;
        }
        $amalAccount->available_balance = BigNumber::new($amount)->add($amalAccount->available_balance)->toString();
        $amalAccount->balance = BigNumber::new($amount)->add($amalAccount->balance)->toString();
        $amalAccount->save();

        $balances = app(UserService::class)->getUserAccounts($userId);
        event(new BalanceUpdated($userId, $balances));
        return $amalAccount;
        // }
    }
}
