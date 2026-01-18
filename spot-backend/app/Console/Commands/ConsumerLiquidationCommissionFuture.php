<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\TransferService;
use App\Models\FutureLiquidationMessage;
use App\Models\LiquidationCommission;
use App\Models\LiquidationCommissionDetail;
use App\Models\User;
use App\Utils;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\KafkaConsumerMessage;
use Exception;

class ConsumerLiquidationCommissionFuture extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'consumer:liquidation-referral-future';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'consumer liquidation referral future';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $topic = Consts::TOPIC_CONSUMER_LIQUIDATION_REFERRAL_FUTURE;
        $handler = new HandlerLiquidationReferralFuture();
        Utils::kafkaConsumer($topic, $handler);
    }
}

class HandlerLiquidationReferralFuture
{
    public function __invoke(KafkaConsumerMessage $message)
    {
        $data = Utils::convertDataKafkaRewardFuture($message);
        DB::beginTransaction();
        try {
            $futureLiquidationMessage = FutureLiquidationMessage::create([
                'user_id' => $data['userId'],
                'coin' => $data['asset'],
                'symbol' => $data['symbol'],
                'rate_with_usdt' => $data['rateWithUsdt'],
                'amount' => $data['liquidationAmount'],
                'executed_time' => $data['timestamp']
            ]);
            if ($futureLiquidationMessage) {
                // get list parrent
                $user = User::find($futureLiquidationMessage->user_id);
                if ($user) {
                    if ($user->affiliateTreeUsers) {
                        $rateByLevel = DB::table('affiliate_trees AS a')
                            ->join('user_rates AS b', 'a.referrer_id', 'b.id')
                            ->where('a.user_id', $user->id)
                            ->pluck('liquidation_rate', 'b.id')
                            ->all();
                        //$date = Carbon::createFromTimestamp($futureLiquidationMessage->executed_time/1000)->toDateString();
                        $date = Carbon::now()->toDateString();

                        foreach ($user->affiliateTreeUsers as $partner) {
                            // check exit
                            $userCalc = User::find($partner['referrer_id']);
                            if (!$userCalc || $userCalc->is_partner != Consts::PARTNER_ACTIVE) {
                                continue;
                            }

                            $rate = $rateByLevel[$userCalc->id] ?? 0;
                            $amount = Utils\BigNumber::new($futureLiquidationMessage->amount)->mul($futureLiquidationMessage->rate_with_usdt)->toString();

                            // check liquidation exist
                            $liquidationCommission = LiquidationCommission::where(['date' => $date, 'user_id' => $userCalc->id])->first();
                            if (!$liquidationCommission) {
                                $liquidationCommission = LiquidationCommission::create([
                                    'date' => $date,
                                    'user_id' => $userCalc->id,
                                    'rate' => $rate,
                                    'amount' => $amount,
                                    'status' => 'init'
                                ]);
                            } else {
                                $liquidationCommission->increment('amount', $amount);
                            }

                            if (!$liquidationCommission) {
                                throw new Exception('HandlerLiquidationReferralFuture:Create:Liquidation');
                            }

                            // check liquidation detail
                            $liquidationCommissionDetail = LiquidationCommissionDetail::where(['liquidation_commission_id' => $liquidationCommission->id, 'user_id' => $user->id])->first();
                            if ($liquidationCommissionDetail) {
                                $liquidationCommissionDetail->increment('amount', $amount);
                            } else {
                                $liquidationCommissionDetail = LiquidationCommissionDetail::create([
                                    'liquidation_commission_id' => $liquidationCommission->id,
                                    'user_id' => $user->id,
                                    'amount' => $amount,
                                ]);
                            }

                            if (!$liquidationCommissionDetail) {
                                throw new Exception('HandlerLiquidationReferralFuture:Create:LiquidationDetail');
                            }
                        }
                    }
                }
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('ConsumerLiquidationCommissionFuture. Failed to create complete_transactions: ' . json_encode($data));
            Log::error($e);
            throw $e;
        }

    }
}
