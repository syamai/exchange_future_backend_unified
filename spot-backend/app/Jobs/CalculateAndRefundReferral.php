<?php

namespace App\Jobs;

use App\Consts;
use App\Utils;
use App\Utils\BigNumber;
use App\Models\OrderTransaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\MultiReferrerDetails;
use App\Http\Services\ReferralService;
use App\Models\CalculateProfit;
use App\Models\CompleteTransaction;
use App\Models\ReferrerHistory;
use Carbon\Carbon;

class CalculateAndRefundReferral extends RedisQueueJob
{
    public $transactionId;
    private $referralService;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($transactionId)
    {
        $data = json_decode($transactionId);
        if ($data[0]) {
            $this->transactionId = $data[0];
        } else {
            $this->transactionId = $transactionId;
        }

        $this->referralService = new ReferralService();
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
//     public function handle()
//     {
//         $transaction = OrderTransaction::find($this->transactionId);
//         if (!$transaction) {
//             Log::error('CalculateAndRefundReferral. Cannot find transaction id: ' . $this->transactionId);
//             return;
//         }
//         if ($this->checkEnableReferralProgram()) {
//             $buyer = User::find($transaction->buyer_id);
//             $seller = User::find($transaction->seller_id);
//             DB::beginTransaction();
//             try {
// //                if ($buyer->referrer_id) {
// //                    if ($transaction->buy_fee_amal) {
// //                        $this->addUserCommission($buyer->id, Consts::CURRENCY_AMAL, $transaction->buy_fee_amal, $transaction);
// //                    } else {
// //                        $this->addUserCommission($buyer->id, $transaction->coin, $transaction->buy_fee, $transaction);
// //                    }
// //                } else {
// //                    if ($transaction->buy_fee_amal) {
// //                        $this->createTotalFeeReferralRecord($transaction->buy_fee_amal, 0, Consts::CURRENCY_AMAL);
// //                    } else {
// //                        $this->createTotalFeeReferralRecord($transaction->buy_fee, 0, $transaction->coin);
// //                    }
// //                }

//                 $this->addUserCommission($buyer->id, $transaction->coin, $transaction->buy_fee, $transaction);

// //                if ($seller->referrer_id) {
// //                    if ($transaction->sell_fee_amal) {
// //                        $this->addUserCommission($seller->id, Consts::CURRENCY_AMAL, $transaction->sell_fee_amal, $transaction);
// //                    } else {
// //                        $this->addUserCommission($seller->id, $transaction->currency, $transaction->sell_fee, $transaction);
// //                    }
// //                } else {
// //                    if ($transaction->sell_fee_amal) {
// //                        $this->createTotalFeeReferralRecord($transaction->sell_fee_amal, 0, Consts::CURRENCY_AMAL);
// //                    } else {
// //                        $this->createTotalFeeReferralRecord($transaction->sell_fee, 0, $transaction->currency);
// //                    }
// //                }
//                 $this->addUserCommission($seller->id, $transaction->currency, $transaction->sell_fee, $transaction);
//                 DB::commit();
//             } catch (Exception $e) {
//                 DB::rollBack();
//                 Log::error('CalculateAndRefundReferral. Failed to calculate commission for transaction: '
//                     . $this->transactionId);
//                 Log::error($e);
//                 throw $e;
//             }
//         } else {
//             if ($transaction->buy_fee_amal) {
//                 $this->createTotalFeeReferralRecord($transaction->buy_fee_amal, 0, Consts::CURRENCY_AMAL);
//             } else {
//                 $this->createTotalFeeReferralRecord($transaction->buy_fee, 0, $transaction->coin);
//             }
//             if ($transaction->sell_fee_amal) {
//                 $this->createTotalFeeReferralRecord($transaction->sell_fee_amal, 0, Consts::CURRENCY_AMAL);
//             } else {
//                 $this->createTotalFeeReferralRecord($transaction->sell_fee, 0, $transaction->currency);
//             }
//         }
//     }

    public function handle() {
        $transaction = OrderTransaction::find($this->transactionId);
        if (!$transaction) {
            Log::error('CalculateAndRefundReferral (create complete_transactions). Cannot find transaction id: ' . $this->transactionId);
            return;
        }
        try{
            DB::beginTransaction();

			$isCalcBuyOrder = true;
			$isCalcSellOrder = true;
			if (env('DISABLE_CALC_REFERRAL_BOT', false)) {
				$buyer = User::find($transaction->buyer_id);
				$seller = User::find($transaction->seller_id);
				$isCalcBuyOrder = $buyer && $buyer->type != Consts::USER_TYPE_BOT;
				$isCalcSellOrder = $seller && $seller->type != Consts::USER_TYPE_BOT;
			}

            $amountUsdt = BigNumber::new($transaction->amount)->mul($transaction->currency_usdt_price);
            $buyFeeUsdt = BigNumber::new($transaction->buy_fee)->mul($transaction->price)->mul($transaction->currency_usdt_price);
            $sellFeeUsdt = BigNumber::new($transaction->sell_fee)->mul($transaction->currency_usdt_price);
            if ($isCalcBuyOrder) {
				CompleteTransaction::create([
					'order_id' => $transaction->buy_order_id,
					'user_id' => $transaction->buyer_id,
					'exchange_user' => $transaction->seller_id,
					'order_transaction_id' => $transaction->id,
					'future_referral_message_id' => null,
					'email' => $transaction->buyer_email,
					'type' => Consts::TYPE_EXCHANGE_BALANCE,
					'transaction_type' => Consts::ORDER_SIDE_BUY,
					'currency' => $transaction->currency,
					'coin' => $transaction->coin,
					'asset_future' => null,
					'symbol_future' => null,
					'price' => $transaction->price,
					'quantity' => $transaction->quantity,
					'amount' => $transaction->amount,
					'fee' => $transaction->buy_fee,
					'price_usdt' => $transaction->currency_usdt_price,
					'amount_usdt' => $amountUsdt,
					'fee_usdt' => $buyFeeUsdt,
					'executed_date' => $transaction->executed_date,
					'is_calculated_direct_commission' => 0,
					'is_calculated_partner_commission' => 0
				]);
                
                $this->referralService->createVolumeReportTransaction($transaction->buyer_id, $amountUsdt, $buyFeeUsdt);
                $this->referralService->createCalculateProfit($transaction->buy_fee, $transaction->coin);
			}

            if ($isCalcSellOrder) {
				CompleteTransaction::create([
					'order_id' => $transaction->sell_order_id,
					'user_id' => $transaction->seller_id,
					'exchange_user' => $transaction->buyer_id,
					'order_transaction_id' => $transaction->id,
					'future_referral_message_id' => null,
					'email' => $transaction->seller_email,
					'type' => Consts::TYPE_EXCHANGE_BALANCE,
					'transaction_type' => Consts::ORDER_SIDE_SELL,
					'currency' => $transaction->currency,
					'coin' => $transaction->coin,
					'asset_future' => null,
					'symbol_future' => null,
					'price' => $transaction->price,
					'quantity' => $transaction->quantity,
					'amount' => $transaction->amount,
					'fee' => $transaction->sell_fee,
					'price_usdt' => $transaction->currency_usdt_price,
					'amount_usdt' => $amountUsdt,
					'fee_usdt' => $sellFeeUsdt,
					'executed_date' => $transaction->executed_date,
					'is_calculated_direct_commission' => 0,
					'is_calculated_partner_commission' => 0
				]);

                $this->referralService->createVolumeReportTransaction($transaction->seller_id, $amountUsdt, $sellFeeUsdt);
                $this->referralService->createCalculateProfit($transaction->sell_fee, $transaction->currency);
			}

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('CalculateAndRefundReferral. Failed to create complete_transactions: '
                . $this->transactionId);
            Log::error($e);
            throw $e;
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

    public function addUserCommission($userId, $currency, $fee, $transaction)
    {
        $user = MultiReferrerDetails::where('user_id', $userId)->first();
        if (!$user) {
            return;
        }
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
//                if (($currency == 'usd' && BigNumber::new($amount)->comp(0.01) < 0) || (BigNumber::new($amount)->comp(0.00000001) < 0)) {
//                    $level++;
//                    continue;
//                }
                $referralFee = BigNumber::new($referralFee)->add($amount)->toString();
                $this->refundAndCreateHistoryRecord(
                    $userIdNeedRefund,
                    $this->getEmail($userIdNeedRefund),
                    $amount,
                    BigNumber::new($commissionPercent),
                    strtoupper($currency),
                    $transaction->id,
                    $userId
                );
                $level++;
            } else {
                break;
            }
        }
        $this->createTotalFeeReferralRecord($fee, $referralFee, $currency);
    }

    public function refundAndCreateHistoryRecord($userId, $email, $amount, $commissionRate, $coin, $transactionId, $transactionOwner)
    {
        $emailOwner = $this->getEmail($transactionOwner);
        DB::beginTransaction();
        try {
            $this->updateBalanceCoin($coin, $userId, $amount);
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
                'coin' => $coin,
                'order_transaction_id' => $transactionId,
                'transaction_owner' => $transactionOwner,
                'transaction_owner_email' => $emailOwner,
                'type' => Consts::TYPE_EXCHANGE_BALANCE,
            ];
            ReferrerHistory::create($data);
        }
    }

    //create column referral_fee in table calculate_profit_daily
    public function createTotalFeeReferralRecord($fee, $referralFee, $coin)
    {
        $netFee = BigNumber::new($fee)->sub($referralFee)->toString();
        $today = Carbon::now()->toDateString();
        $hasRecord = CalculateProfit::where('date', $today)
            ->where('coin', $coin)
            ->first();
        if (!$hasRecord) {
            return CalculateProfit::create([
                'date' => $today,
                'coin' => $coin,
                'receive_fee' => $fee,
                'referral_fee' => $referralFee,
                'net_fee' => $netFee
            ]);
        }
        $record = CalculateProfit::where('date', $today)
            ->where('coin', $coin)
            ->lockForUpdate()
            ->first();
        return CalculateProfit::where('date', $today)
            ->where('coin', $coin)
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

    public function updateBalanceCoin($coin, $userId, $amount)
    {
        $table = 'spot_' . strtolower($coin) . '_accounts';
        $balanceRecord = $this->getBalance($table, $userId);
        $availableBalance = $balanceRecord->available_balance;
        $balance = $balanceRecord->balance;
        return DB::table($table)
            ->where('id', $userId)
            ->update([
                'balance' => BigNumber::new($balance)->add($amount),
                'available_balance' => BigNumber::new($availableBalance)->add($amount),
                'updated_at' => Carbon::now(),
            ]);
    }

    public function getBalance($table, $userId)
    {
        return DB::connection('master')->table($table)
            ->where('id', $userId)
            ->lockForUpdate()
            ->first();
    }
}
