<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\ReferralService;
use App\Models\CalculateProfit;
use App\Models\CompleteTransaction;
use App\Models\FutureReferralMessage;
use App\Models\MultiReferrerDetails;
use App\Models\ReferrerHistory;
use App\Models\User;
use App\Utils;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\KafkaConsumerMessage;

class CalculateReferralFuture extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'referral:future';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $topic = Consts::TOPIC_CONSUMER_FUTURE_REFERRAL;
        $handler = new HandlerReferral();
        Utils::kafkaConsumer($topic, $handler);
    }
}

class HandlerReferral
{
    private $referralService;

    public function __construct()
    {
        $this->referralService = new ReferralService();
    }

    public function __invoke(KafkaConsumerMessage $message)
    {
        $data = Utils::convertDataKafka($message);

        try {
            DB::beginTransaction();

            $futureReferralMessage = FutureReferralMessage::create([
                'buyer_id' => $data['data']['buyerId'],
                'seller_id' => $data['data']['sellerId'],
                'coin' => $data['data']['asset'],
                'buy_fee' => $data['data']['buyerFee'],
                'sell_fee' => $data['data']['sellerFee'],
                'executed_date' => Carbon::now(),
                'amount' => $data['data']['volume'],
                'symbol' => $data['data']['symbol'],
                'rate_with_usdt' => $data['data']['rateWithUsdt'],
				'buy_order_id' => isset($data['data']['buyOrderId']) ? $data['data']['buyOrderId'] : null,
				'sell_order_id' => isset($data['data']['sellOrderId']) ? $data['data']['sellOrderId'] : null
            ]);

            //$disableBot = env('DISABLE_CALC_REFERRAL_BOT', false);
            $isCalcBuyOrder = true;
			$isCalcSellOrder = true;
			if (env('DISABLE_CALC_REFERRAL_BOT', false)) {
				$buyer = User::find($futureReferralMessage->buyer_id);
				$seller = User::find($futureReferralMessage->seller_id);
				$isCalcBuyOrder = $buyer && $buyer->type != Consts::USER_TYPE_BOT;
				$isCalcSellOrder = $seller && $seller->type != Consts::USER_TYPE_BOT;
			}

    
            $amountUsdt = BigNumber::new($futureReferralMessage->amount)->mul($futureReferralMessage->rate_with_usdt);
            $buyFeeUsdt = BigNumber::new($futureReferralMessage->buy_fee)->mul($futureReferralMessage->rate_with_usdt);
            $sellFeeUsdt = BigNumber::new($futureReferralMessage->sell_fee)->mul($futureReferralMessage->rate_with_usdt);
            if ($isCalcBuyOrder) {
				CompleteTransaction::create([
					'order_id' => $futureReferralMessage->buy_order_id,
					'user_id' => $futureReferralMessage->buyer_id,
					'exchange_user' => $futureReferralMessage->seller_id,
					'order_transaction_id' => null,
					'future_referral_message_id' => $futureReferralMessage->id,
					'email' => $futureReferralMessage->buyer->email ?? '',
					'type' => Consts::TYPE_FUTURE_BALANCE,
					'transaction_type' => Consts::ORDER_SIDE_BUY,
					'currency' => $futureReferralMessage->coin,
					'coin' => $futureReferralMessage->coin,
					'asset_future' => $futureReferralMessage->coin,
					'symbol_future' => $futureReferralMessage->symbol,
					'price' => null,
					'quantity' => null,
					'amount' => $futureReferralMessage->amount,
					'fee' => $futureReferralMessage->buy_fee,
					'price_usdt' => $futureReferralMessage->rate_with_usdt,
					'amount_usdt' => $amountUsdt,
					'fee_usdt' => $buyFeeUsdt,
					'executed_date' => $futureReferralMessage->executed_date,
					'is_calculated_direct_commission' => 0,
					'is_calculated_partner_commission' => 0
				]);

                $this->referralService->createVolumeReportTransaction($futureReferralMessage->buyer_id, $amountUsdt, $buyFeeUsdt);
                $this->referralService->createCalculateProfit($futureReferralMessage->buy_fee, $futureReferralMessage->coin);

			}

            if ($isCalcSellOrder) {
				CompleteTransaction::create([
					'order_id' => $futureReferralMessage->sell_order_id,
					'user_id' => $futureReferralMessage->seller_id,
					'exchange_user' => $futureReferralMessage->buyer_id,
					'order_transaction_id' => null,
					'future_referral_message_id' => $futureReferralMessage->id,
					'email' => $futureReferralMessage->seller->email ?? '',
					'type' => Consts::TYPE_FUTURE_BALANCE,
					'transaction_type' => Consts::ORDER_SIDE_SELL,
					'currency' => $futureReferralMessage->coin,
					'coin' => $futureReferralMessage->coin,
					'asset_future' => $futureReferralMessage->coin,
					'symbol_future' => $futureReferralMessage->symbol,
					'price' => null,
					'quantity' => null,
					'amount' => $futureReferralMessage->amount,
					'fee' => $futureReferralMessage->sell_fee,
					'price_usdt' => $futureReferralMessage->rate_with_usdt,
					'amount_usdt' => $amountUsdt,
					'fee_usdt' => $sellFeeUsdt,
					'executed_date' => $futureReferralMessage->executed_date,
					'is_calculated_direct_commission' => 0,
					'is_calculated_partner_commission' => 0
				]);
                
                $this->referralService->createVolumeReportTransaction($futureReferralMessage->seller_id, $amountUsdt, $sellFeeUsdt);
                $this->referralService->createCalculateProfit($futureReferralMessage->sell_fee, $futureReferralMessage->coin);
			}

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('CalculateReferralFuture. Failed to create complete_transactions: '
                . json_encode($data));
            Log::error($e);
            throw $e;
        }

        // $buyerFee = $data['data']['buyerFee'];
        // $sellerFee = $data['data']['sellerFee'];
        // $currency = $data['data']['asset'];
        // if ($currency) {
        //     if ($this->checkEnableReferralProgram()) {
        //         $buyer = User::find($data['data']['buyerId']);
        //         $seller = User::find($data['data']['sellerId']);
        //         DB::beginTransaction();
        //         try {
                    // $this->addUserCommission($buyer->id ?? null, $currency, $buyerFee);
                    // $this->addUserCommission($seller->id ?? null, $currency, $sellerFee);
        //             DB::commit();
        //         } catch (Exception $e) {
        //             DB::rollBack();
        //             Log::error('CalculateAndRefundReferral. Failed to calculate commission for transaction: '
        //                 . $this->transactionId);
        //             Log::error($e);
        //             throw $e;
        //         }
        //     }
        // }
    }

    public function checkEnableReferralProgram()
    {
        $setting = app(ReferralService::class)->getReferralSettings();
        if (!$setting) {
            return false;
        }
        return $setting->enable;
    }

    public function addUserCommission($userId, $currency, $fee)
    {
        if(!$userId){
            return;
        }
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
                $message = [
                    "userId" => $userIdNeedRefund,
                    "amount" => $amount->toString(),
                    "asset" => $currency,
                    "type" => "REFERRAL"
                ];
                $topic = Consts::TOPIC_PRODUCER_FUTURE_REFERRAL;
                Utils::kafkaProducer($topic, $message);
                $this->refundAndCreateHistoryRecord(
                    $userIdNeedRefund,
                    $this->getEmail($userIdNeedRefund),
                    $amount,
                    BigNumber::new($commissionPercent),
                    strtoupper($currency),
                    null,
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
        $data = [
            'user_id' => $userId,
            'email' => $email,
            'amount' => $amount,
            'commission_rate' => $commissionRate,
            'coin' => $coin,
            'order_transaction_id' => $transactionId,
            'transaction_owner' => $transactionOwner,
            'transaction_owner_email' => $emailOwner,
            'type' => Consts::TYPE_FUTURE_BALANCE,
        ];
        ReferrerHistory::create($data);
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

    public function getSettingForUser($userId)
    {
        $setting = app(ReferralService::class)->getReferralSettings();
        $user = MultiReferrerDetails::where('user_id', $userId)->first();
        $condition = $setting->number_people_in_next_program;
        $totalReferrer = $user->number_of_referrer_lv_1 ?? 0;

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

    public function getEmail($userId)
    {
        $user = User::find($userId);
        return $user->email;
    }
}
