<?php

namespace App\Jobs;

use App\Consts;
use App\Http\Requests\TransferRequest;
use App\Http\Services\ReferralService;
use App\Http\Services\TransferService;
use App\Models\CalculateProfit;
use App\Models\MultiReferrerDetails;
use App\Models\OrderTransaction;
use App\Models\ReferrerHistory;
use App\Models\User;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use ErrorException;

class CalculateReferralCommissionFuture extends RedisQueueJob
{
    private $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    protected static function shouldAddToQueue()
    {
        return true;
    }

    protected static function getNextRun()
    {
        return static::currentMilliseconds() + 10000;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $buyer = User::find($this->data['buyer_id']);
        $seller = User::find($this->data['seller_id']);
        if (!$buyer || !$seller) {
            Log::error('CalculateReferralCommission. Cannot find user id');
            return;
        }
        $symbol = $this->data['symbol'];
        $asset =  $this->data['asset'];
        $buyFee = $this->data['buy_fee'];
        $sellFee = $this->data['sell_fee'];
        if ($this->checkEnableReferralProgram()) {
            DB::beginTransaction();
            try {
                if ($buyer->referrer_id) {
                    $this->addUserCommission($buyer->id, $symbol, $buyFee, $asset);
                } else {
                    $this->createTotalFeeReferralRecord($buyFee, 0, $symbol);
                }

                if ($seller->referrer_id) {
                    $this->addUserCommission($seller->id, $symbol, $sellFee, $asset);
                } else {
                    $this->createTotalFeeReferralRecord($sellFee, 0, $symbol);
                }
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Log::error('CalculateAndRefundReferral. Failed to calculate commission for future ');
                Log::error($e);
                throw $e;
            }
        } else {
            if ($buyFee) {
                $this->createTotalFeeReferralRecord($buyFee, 0, $symbol);
            }
            if ($sellFee) {
                $this->createTotalFeeReferralRecord($sellFee, 0, $symbol);
            }
        }
    }


    public function getSettingForUser($userId)
    {
        $setting = app(ReferralService::class)->getReferralSettings();
        $user = MultiReferrerDetails::where('user_id', $userId)->first();
        $condition = $setting->number_people_in_next_program;
        $totalReferrer = $user->number_of_referrer_lv_1;

        if ($totalReferrer < $condition) {
            return $setting = [
                'refund_percent_at_level_1' => $setting->refund_percent_at_level_1,
                'refund_percent_at_level_2' => $setting->refund_percent_at_level_2,
                'refund_percent_at_level_3' => $setting->refund_percent_at_level_3,
                'refund_percent_at_level_4' => $setting->refund_percent_at_level_4,
                'refund_percent_at_level_5' => $setting->refund_percent_at_level_5,
            ];
        } else {
            return $setting = [
                'refund_percent_at_level_1' => $setting->refund_percent_in_next_program_lv_1,
                'refund_percent_at_level_2' => $setting->refund_percent_in_next_program_lv_2,
                'refund_percent_at_level_3' => $setting->refund_percent_in_next_program_lv_3,
                'refund_percent_at_level_4' => $setting->refund_percent_in_next_program_lv_4,
                'refund_percent_at_level_5' => $setting->refund_percent_in_next_program_lv_5,
            ];
        }
    }

    public function addUserCommission($userId, $symbol, $fee, $asset)
    {
        $user = MultiReferrerDetails::where('user_id', $userId)->first();
        $setting = app(ReferralService::class)->getReferralSettings();
        $refundPercent = $setting->refund_rate;
        $referralFee = 0; // Init total fee paid for Referral

        //refund referral commisson
        $numberOfLevel = $setting->number_of_levels;
        $level = 1;
        while ($level <= $numberOfLevel) {
            $getIdAt = 'referrer_id_lv_' . $level;
            $userIdNeedRefund = $user->$getIdAt;
            if ($userIdNeedRefund) {
                $settingForUser = $this->getSettingForUser($userIdNeedRefund);
                $getPercentAt = 'refund_percent_at_level_' . $level;
                $commissionPercent = $settingForUser[$getPercentAt];
                $amount = BigNumber::new($fee)->mul($refundPercent)->mul($commissionPercent)->div(100 * 100);
                if (($asset == 'usd' && BigNumber::new($amount)->comp(0.01) < 0) || (BigNumber::new($amount)->comp(0.00000001) < 0)) {
                    $level++;
                    continue;
                }
                $referralFee = BigNumber::new($referralFee)->add($amount)->toString();
                $this->refundAndCreateHistoryRecord(
                    $userIdNeedRefund,
                    $this->getEmail($userIdNeedRefund),
                    $amount,
                    BigNumber::new($commissionPercent),
                    strtoupper($symbol),
                    $userId,
                    $asset
                );
                $level++;
            } else {
                break;
            }
        }
        $this->createTotalFeeReferralRecord($fee, $referralFee, $symbol);
    }

    public function refundAndCreateHistoryRecord($userId, $email, $amount, $commissionRate, $symbol, $transactionOwner, $asset)
    {
        $emailOwner = $this->getEmail($transactionOwner);
        DB::beginTransaction();
        try {
            $transfer = new TransferService();
            $transfer->updateBalanceReferral((object)[
                'asset' => strtolower($asset),
                'amount' => $amount,
            ]);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            logger()->error($e);
        } finally {
            $data = [
                'user_id' => $userId,
                'email' => $email,
                'amount' => $amount,
                'commission_rate' => $commissionRate,
                'symbol' => $symbol,
                'transaction_owner' => $transactionOwner,
                'transaction_owner_email' => $emailOwner,
                'type' => Consts::TYPE_FUTURE_BALANCE,
                'asset_future' => $asset
            ];
            ReferrerHistory::create($data);
        }
    }

    //create column referral_fee in table calculate_profit_daily
    public function createTotalFeeReferralRecord($fee, $referralFee, $symbol)
    {
        $netFee = BigNumber::new($fee)->sub($referralFee)->toString();
        $today = Carbon::now()->toDateString();
        $hasRecord = CalculateProfit::where('date', $today)
            ->where('symbol', $symbol)
            ->first();
        if (!$hasRecord) {
            return CalculateProfit::create([
                'date' => $today,
                'symbol' => $symbol,
                'receive_fee' => $fee,
                'referral_fee' => $referralFee,
                'net_fee' => $netFee
            ]);
        }
        $record = CalculateProfit::where('date', $today)
            ->where('symbol', $symbol)
            ->lockForUpdate()
            ->first();
        return CalculateProfit::where('date', $today)
            ->where('symbol', $symbol)
            ->update([
                'receive_fee' => BigNumber::new($record->receive_fee)->add($fee),
                'referral_fee' => BigNumber::new($record->referral_fee)->add($referralFee),
                'net_fee' => BigNumber::new($record->net_fee)->add($netFee)
            ]);
    }

    public function getEmail($userId)
    {
        $user = User::find($userId);
        return $user->email;
    }

    public function checkEnableReferralProgram()
    {
        $setting = app(ReferralService::class)->getReferralSettings();
        if (!$setting) {
            return false;
        }
        return $setting->enable;
    }
}
