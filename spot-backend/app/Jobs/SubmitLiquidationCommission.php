<?php

namespace App\Jobs;

use App\Models\LiquidationCommission;
use App\Models\SpotCommands;
use App\Consts;
use App\Utils;
use App\Utils\BigNumber;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class SubmitLiquidationCommission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $id = null;

    /**
     * Create a new job instance.
     * @param $data
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            logger()->info('Submit Liquidation Commission Request ==============' . json_encode($this->id));
            $asset = Consts::CURRENCY_USDT;
            $liquidationCommission = LiquidationCommission::find($this->id);
            if ($liquidationCommission->status != 'pending') {
                logger("Status liquidationCommission id ({$this->id}): {$liquidationCommission->status}");
                return;
            }


            $amount = $liquidationCommission->amount_receive;

            $tableBalance = 'spot_' . $asset . '_accounts';
            $amount = BigNumber::new($amount)->toString();
            if ($amount <= 0) {
                //update status completed
                $liquidationCommission->update(['status' => 'completed', 'complete_at' => Carbon::now()]);
                logger("Amount liquidationCommission id ({$this->id}): {$amount}");
                return;
            }
            $userId = $liquidationCommission->user_id;

            /*$balance = DB::table($tableBalance)->where(['id' => $userId])->first();
            $availableBalance = $balance->available_balance;*/

            DB::transaction(function () use ($tableBalance, $userId, $asset, $amount, $liquidationCommission) {
                DB::table($tableBalance)
                    ->lockForUpdate()
                    ->where([
                        'id' => $userId
                    ])->update([
                        'balance' => DB::raw('balance + ' . $amount),
                        'available_balance' => DB::raw('available_balance + ' . $amount),
                    ]);

                try {
                    $matchingJavaAllow = env("MATCHING_JAVA_ALLOW", false);
                    if ($matchingJavaAllow) {
                        //send kafka ME Deposit
                        $typeName = 'deposit';

                        $payload = [
                            'type' => $typeName,
                            'data' => [
                                'userId' => $userId,
                                'coin' => $asset,
                                'amount' => $amount,
                                'liqCommissionId' => $liquidationCommission->id
                            ]
                        ];

                        $command = SpotCommands::create([
                            'command_key' => md5(json_encode($payload)),
                            'type_name' => $typeName,
                            'user_id' => $userId,
                            'obj_id' => $liquidationCommission->id,
                            'payload' => json_encode($payload),
                        ]);
                        if (!$command) {
                            throw new Exception('can not create command');
                        }

                        $payload['data']['commandId'] = $command->id;
                        Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_COMMAND, $payload);
                        /*if (env('SEND_BALANCE_LOG_TO_WALLET', false)) {
                            SendBalanceLogToWallet::dispatch([
                                'userId' => $userId,
                                'walletType' => 'SPOT',
                                'type' => 'DEPOSIT',
                                'currency' => $asset,
                                'currencyAmount' => $amount,
                                'currencyFeeAmount' => "0",
                                'currencyAmountWithoutFee' => $amount,
                                'date' => Utils::currentMilliseconds()
                            ])->onQueue(Consts::QUEUE_BALANCE_WALLET);
                        }*/
                    }
                } catch (\Exception $ex) {
                    Log::error($ex);
                    Log::error("++++++++++++++++++++ SubmitLiquidationCommission Deposit: $userId, coin: $asset, amount: $amount");
                }
                $liquidationCommission->update(['status' => 'completed', 'complete_at' => Carbon::now()]);

            });

        } catch (\Exception $e) {
            logger()->error("SUBMIT LIQUIDATION COMMISSION FAIL ======== " . $e->getMessage());
            throw $e;
        }
    }
}
