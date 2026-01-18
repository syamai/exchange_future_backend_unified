<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SendAirdropBalanceJob;
use App\Models\User;
use App\Consts;
use App\Models\AirdropHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Utils\BigNumber;
use App\Http\Services\AirdropService;

class CalculateAMALPercentHolding extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calculate:amal_holding {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate percent of amal holding by user';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $dateExcution = $this->argument('date');
        $date = @$dateExcution ?? Carbon::now()->toDateString();
        $setting = app(AirdropService::class)->getAirdropSetting();
        if (!$setting) {
            return;
        }
        $enable = $setting->enable;
        if (!$enable) {
            return;
        }
        if (!$this->HasAirdrop($date)) {
            $users = User::get();
            foreach ($users as $user) {
                $amalPercent = $this->calculateAmalPercent($user->id);
                if ($amalPercent) {
                    $this->createHistoryLockBalance($user, $amalPercent, $date);
                }
            }

            $listRecords = $this->getListRecordNotSuccess();
            foreach ($listRecords as $record) {
                SendAirdropBalanceJob::dispatch($record)->onQueue(Consts::QUEUE_AIRDROP);
            }
        }
    }

    public function checkAmountBalance()
    {
        $airdropSetting = app(AirdropService::class)->getAirdropSetting();

        return (BigNumber::new($airdropSetting->payout_amount)->comp($airdropSetting->total_paid) > 0);
    }

    public function HasAirdrop($date)
    {
        $hasAirdrop = AirdropHistory::where('last_unlocked_date', $date)->first();
        if ($hasAirdrop) {
            return true;
        }
        return false;
    }


    public function calculateAmalPercent($userId)
    {
        $airdropSetting = app(AirdropService::class)->getAirdropSetting();
        if (!$airdropSetting) {
            return 0;
        }
        $minHoldAmal = $airdropSetting->min_hold_amal;
        if (!$minHoldAmal) {
            return 0;
        }
        $amalBalance = 0;
        $amalBalanceBonus = 0;
        $airDropBalance = DB::table('airdrop_amal_accounts')->where('id', $userId)->first();
        if ($airDropBalance) {
            $amalBalance = $airDropBalance->balance;
            $amalBalanceBonus = $airDropBalance->balance_bonus;
        }

        if (BigNumber::new($amalBalance)->add($amalBalanceBonus)->comp($minHoldAmal) >= 0) {
            $totalAmal = $airdropSetting->total_supply;

            if (!$totalAmal) {
                return 0;
            }

            return BigNumber::new($amalBalance)->add($amalBalanceBonus)->div($totalAmal)->mul(100)->toString();
        }

        return 0;
    }

    public function createHistoryLockBalance($user, $amalPercent, $date)
    {
        $data = $this->getData($user, $amalPercent, $date);
        return AirdropHistory::create($data);
    }

    public function getData($user, $amalPercent, $date)
    {
        $airdropSetting = app(AirdropService::class)->getAirdropSetting();
        $payoutAmount = $airdropSetting->payout_amount;
        $data = [
            'user_id' => $user->id,
            'email' => $user->email,
            'currency' => $airdropSetting->currency,
            'status' => Consts::AIRDROP_UNPAID,
            'amount' => BigNumber::new($amalPercent)->mul($payoutAmount)->div(100),
            'last_unlocked_date' => $date
        ];

        return $data;
    }

    public function getListRecordNotSuccess()
    {

        return AirdropHistory::where('status', Consts::AIRDROP_UNPAID)->get();
    }

    public function checkPeriod($record)
    {
        $period = $this->getPeriod($record->user_id);
        $lastUnlockedDate = $record->last_unlocked_date;
        $diffDay = Carbon::now()->diffInDays($lastUnlockedDate);
        return ($diffDay >= $period);
    }

    public function getPeriod($userId)
    {
        $airdropService = app(AirdropService::class);
        $userSetting = $airdropService->getAirdropUserSetting($userId);
        if ($userSetting) {
            return $userSetting->period;
        }

        $setting = $airdropService->getAirdropSetting();

        return $setting->period;
    }
}
