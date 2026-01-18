<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Consts;
use App\Models\AirdropHistoryLockBalance;
use App\Models\AirdropUserSetting;
use App\Jobs\UnlockBalanceAirdrop;
use Carbon\Carbon;
use App\Http\Services\AirdropService;

class UnlockAirdropBalance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:airdrop_amal_balance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unlock AMAL amount in airdrop for user';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $setting = app(AirdropService::class)->getAirdropSetting();
        if (!$setting) {
            return;
        }
        $enable = $setting->enable;
        if (!$enable) {
            return;
        }
        $records = AirdropHistoryLockBalance::where('status', '!=', Consts::AIRDROP_SUCCESS)->get();
        foreach ($records as $record) {
            if ($this->checkPeriod($record)) {
                $this->createJobUnlockBalance($record);
            }
        }
    }

    public function checkPeriod($record)
    {
        if ($record->type == Consts::AIRDROP_TYPE_SPECIAL || $record->type == Consts::AIRDROP_TYPE_ADMIN) {
            $check = $this->checkStartUnlock($record);
            if (!$check) {
                return false;
            }
            $period = config('airdrop.period_for_special_type');
        } else {
            $period = $this->getPeriod($record->user_id);
        }
        $lastUnlockedDate = $record->last_unlocked_date;
        $diffDay = Carbon::now()->diffInDays($lastUnlockedDate);

        return ($diffDay >= $period);
    }

    public function checkStartUnlock($record)
    {
        $enable = $record->type == Consts::AIRDROP_TYPE_SPECIAL ? config('airdrop.enable_special_type_unlock') : config('airdrop.enable_admin_type_unlock');
        if (!$enable) {
            return;
        }
        $startUnlock = config('airdrop.start_unlock');
        $condition = Carbon::today()->diffInDays($startUnlock, false);
        if ($condition < 0) {
            return true;
        }
        if ($condition == 0) {
            AirdropHistoryLockBalance::where('id', $record->id)
                ->update([
                    'last_unlocked_date' => Carbon::today()->toDateString()
                ]);
        }
        return false;
    }

    public function getPeriod($userId)
    {
        $userSetting = app(AirdropService::class)->getAirdropUserSetting($userId);
        if ($userSetting) {
            return $userSetting->period;
        }

        $setting = app(AirdropService::class)->getAirdropSetting();

        return $setting->period;
    }

    public function createJobUnlockBalance($record)
    {
        if ($record->type == Consts::AIRDROP_TYPE_SPECIAL || $record->type == Consts::AIRDROP_TYPE_ADMIN) {
            return UnlockBalanceAirdrop::dispatch($record->id, config('airdrop.unlock_percent_for_special_type'))->onQueue(Consts::QUEUE_AIRDROP);
        }
        $commonSetting = app(AirdropService::class)->getAirdropSetting();
        $unlockPercent = $commonSetting->unlock_percent;
        $userSetting = AirdropUserSetting::where('user_id', $record->user_id)->first();
        if ($userSetting) {
            $unlockPercent = $userSetting->unlock_percent;
        }
        UnlockBalanceAirdrop::dispatch($record->id, $unlockPercent)->onQueue(Consts::QUEUE_AIRDROP);
    }
}
