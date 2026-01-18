<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Consts;
use App\Models\UserFeeLevel;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Services\MasterdataService;
use App\Utils\BigNumber;
use Illuminate\Support\Facades\Cache;

class CalculateUserFeeLevel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calculate:user_fee_level';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate user fee level';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $count = 0;
        $this->clearCacheUserFeeLevel();
        logger('calculate user fee levels');
        Cache::forget('calculateUserFeeLevel_LastId');
        while ($count < 3) {
            try {
                $this->calculateUserFeeLevel();
                return 0;
            } catch (Exception $exception) {
                logger()->error($exception);
                // TODO: Send a email to admin
                // Mail::to
                $count += 1;
            }
        }
    }

    private function clearCacheUserFeeLevel()
    {
        $userIds = User::select('id')->where('status', User::STATUS_ACTIVE)->pluck('id');
        foreach ($userIds as $key => $userId) {
            Cache::forget("UserFeeLevel{$userId}");
        }
    }

    private function calculateUserFeeLevel()
    {
        $calculateDate = Carbon::now(Consts::DEFAULT_TIMEZONE)->addDay();

        $query = User::where('status', User::STATUS_ACTIVE)->orderBy('id', 'asc');

        if (Cache::has('calculateUserFeeLevel_LastId')) {
            $lastId = Cache::get('calculateUserFeeLevel_LastId');
            $query->where('id', '>', $lastId);
        }

        $query->chunk(1000, function ($users) use ($calculateDate) {
            foreach ($users as $user) {
                $mgcAccount = DB::table('mgc_accounts')
                                ->where('id', $user->id)
                                ->first();

                if (!$mgcAccount) {
                    Cache::forever('calculateUserFeeLevel_LastId', $user->id);
                    continue;
                }

                $mgcHoldingAmount = $mgcAccount->balance;

                $this->updateCommissionRateForUser($user->id, $mgcHoldingAmount);

                $feeLevel = $this->calculateFeeLevel($mgcHoldingAmount);
                $timestamp = $calculateDate->startOfDay()->timestamp * 1000;

                UserFeeLevel::firstOrCreate([
                    'user_id' => $user->id,
                    'active_time' => $timestamp,
                ], [ 'fee_level' => $feeLevel ]);

                Cache::forever('calculateUserFeeLevel_LastId', $user->id);
            }
        });
    }

    private function calculateFeeLevel($mgcHoldingAmount)
    {
        $feeLevels = MasterdataService::getOneTable('fee_levels');
        $level = 1;
        $feeLevels->sortByDesc('level')->each(function ($feeLevel) use ($mgcHoldingAmount, &$level) {
            if (BigNumber::new($mgcHoldingAmount)->comp($feeLevel->mgc_amount) >= 0) {
                $level = $feeLevel->level;
                return false;
            }
        });
        return $level;
    }

    private function updateCommissionRateForUser($userId, $mgcHolding)
    {
        $key = Consts::COMMISSION_RATE_KEY . $userId;
        Cache::put($key, $mgcHolding >= Consts::MGC_HOLDING ? Consts::COMMISSION_RATE_MAX : Consts::COMMISSION_RATE_DEFAULT);
    }
}
