<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\MasterdataService;
use App\Models\CoinsConfirmation;
use App\Models\KrwTransaction;
use App\Models\Process;
use App\Models\User;
use App\Utils;
use App\Utils\BigNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Transaction\Models\Transaction;

class KafkaMEInit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kafka_me:init {type?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command Send data init to ME ';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $matchingJavaAllow = env("MATCHING_JAVA_ALLOW", false);
        if (!$matchingJavaAllow) {
            return Command::SUCCESS;
        }
        //$type = $this->argument('type') ?? 'all';
		Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_INIT, ['type' => "init"]);

        //send kafka init data
        /*if ($type == 'all') {
            //Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_INIT, ['type' => "init"]);
        }

        // send data pair
        if (in_array($type, ['all', 'pair'])) {
            $pairs = [
                'type' => "pair",
                'data' => []
            ];
            $currencyCoins = MasterdataService::getOneTable('coin_settings');
            $feeSettings = MasterdataService::getOneTable('market_fee_setting');
            foreach ($currencyCoins as $currencyCoin) {
                $feeSetting = $feeSettings->filter(function ($item) use ($currencyCoin) {
                    return $item->coin == $currencyCoin->coin && $item->currency == $currencyCoin->currency;
                })->first();
                $makerFee = 0;
                $takerFee = 0;
                if ($feeSetting) {
                    $makerFee = $feeSetting->fee_maker;
                    $takerFee = $feeSetting->fee_taker;
                }
                $pairs['data'][] = [
                    'currency' => $currencyCoin->currency,
                    'coin' => $currencyCoin->coin,
                    'minimum_quantity' => $currencyCoin->minimum_quantity,
                    'price_precision' => $currencyCoin->price_precision,
                    'minimum_amount' => $currencyCoin->minimum_amount,
                    'quantity_precision' => $currencyCoin->quantity_precision,
                    'maker_fee_percent' => $makerFee,
                    'taker_fee_percent' => $takerFee
                ];
            }

            //send kafka
            Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_BE_INIT, $pairs);
			Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_BE_INIT, ['type' => "pair-end"]);
        }

        //send data account
        if (in_array($type, ['all', 'account'])) {
            // get coin amount pending withdraw
            $trans = Transaction::where("status", Consts::TRANSACTION_STATUS_PENDING)
                ->filterWithdraw()
                ->groupBy(['user_id', 'currency'])
                ->selectRaw('user_id, currency, sum(amount - fee) as amount')
                ->get();
            $userWithdraws = [];

            foreach ($trans as $tran) {
                $userWithdraws[$tran->user_id][$tran->currency] = BigNumber::new($tran->amount)->mul(-1)->toString();
            }

            //get withdraw krw

            $transKrw = KrwTransaction::where(
                [
                    'status' => Consts::TRANSACTION_STATUS_PENDING,
                    'type' => Consts::TRANSACTION_TYPE_WITHDRAW
                ])
                ->groupBy(['user_id'])
                ->selectRaw('user_id, sum(amount_usdt) as amount')
                ->get();
            foreach ($transKrw as $tran) {
                if (!isset($userWithdraws[$tran->user_id]) || !isset($userWithdraws[$tran->user_id][Consts::CURRENCY_USDT])) {
                    $userWithdraws[$tran->user_id][Consts::CURRENCY_USDT] = 0;
                }
                $userWithdraws[$tran->user_id][Consts::CURRENCY_USDT] = BigNumber::new($tran->amount)->add($userWithdraws[$tran->user_id][Consts::CURRENCY_USDT])->toString();
            }

            $currencies = CoinsConfirmation::query()->select('coin')->pluck('coin');
            $sendAccount = false;
            User::with('AccountProfileSetting')
                ->chunkById(100, function($members) use($currencies, $userWithdraws, &$sendAccount) {
                    $users = [];
                    $userIds = $members->pluck("id");
                    foreach ($members as $member) {
                        $spot_trading_fee_allow = $member->AccountProfileSetting->spot_trading_fee_allow ?? true;
                        $users[$member->id] = [
                            'userId' => $member->id,
                            'spotTradingFeeAllow' => $spot_trading_fee_allow  ? true : false,
                            'assets' => []
                        ];
                    }

                    foreach ($currencies as $currency) {
                        $currencyTable = 'spot_' . $currency . '_accounts';
                        $balances = DB::connection('master')
                            ->table($currencyTable)
                            ->select(['id', 'balance', 'available_balance'])
                            ->whereIn('id', $userIds)
                            //->where('available_balance', '>', 0)
                            ->get();
                        foreach($balances as $balance) {
                            if (!isset($users[$balance->id])) {
                                $users[$balance->id] = [
                                    'userId' => $balance->id,
                                    'assets' => []
                                ];
                            }
                            if ($balance->balance > 0) {
                                $available_balance = BigNumber::new($balance->balance)->toString();
                                if (isset($userWithdraws[$balance->id]) && isset($userWithdraws[$balance->id][$currency])) {
                                    $available_balance = BigNumber::new($available_balance)->sub($userWithdraws[$balance->id][$currency])->toString();
                                }
                                if ($available_balance) {
                                    $users[$balance->id]['assets'][strtoupper($currency)] = $available_balance;
                                }
                            }
                        }
                    }
                    if ($users) {
                        $accounts = [
                            'type' => "account",
                            'data' => array_values($users)
                        ];

                        //send kafka
                        Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_BE_INIT, $accounts);
						$sendAccount = true;
                    }
                });
            if ($sendAccount) {
				Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_BE_INIT, ['type' => "account-end"]);
			}

        }

        // order
        if (in_array($type, ['all', 'order'])) {
            $userIdAuto = env('FAKE_USER_AUTO_MATCHING', -1);
            $currencyCoins = MasterdataService::getOneTable('coin_settings');
            $pairs = [];
            foreach ($currencyCoins as $currencyCoin) {
                $pairs[Utils::getKeySymbolSpot($currencyCoin->currency, $currencyCoin->coin)] = [
                    'currency' => $currencyCoin->currency,
                    'coin' => $currencyCoin->coin,
                    'minimum_quantity' => $currencyCoin->minimum_quantity,
                    'price_precision' => $currencyCoin->price_precision,
                    'minimum_amount' => $currencyCoin->minimum_amount,
                    'quantity_precision' => $currencyCoin->quantity_precision,
                ];
            }

//            $orders = DB::table('orders')
//                //->where('user_id', '!=', $userIdAuto)
//                ->whereIn('status', [Consts::ORDER_STATUS_PENDING, Consts::ORDER_STATUS_EXECUTING])
//                //->whereIn('type', [Consts::ORDER_TYPE_LIMIT, Consts::ORDER_TYPE_STOP_LIMIT])
//                ->orderBy('updated_at', 'asc')
//                ->select(['id', 'user_id', 'quantity', 'executed_quantity', 'type', 'stop_condition', 'trade_type', 'base_price', 'price', 'currency', 'coin', 'status', 'updated_at'])
//                ->get();
//            if ($orders->isNotEmpty()) {
//                foreach($orders->chunk(100) as $items) {
//                    $dataOrders = [
//                        'type' => "order",
//                        'data' => []
//                    ];
//                    foreach ($items as $order) {
//                        $keySymbol = Utils::getKeySymbolSpot($order->currency, $order->coin);
//                        $pairInfo = isset($pairs[$keySymbol]) ? $pairs[$keySymbol] : null;
//                        $pricePrecision = $pairInfo ? $pairInfo['price_precision'] : 1;
//                        $quantityPrecision = $pairInfo ? $pairInfo['quantity_precision'] : 1;
//                        $dataOrders['data'][] = [
//                            'orderId' => $order->id,
//                            'userId' => $order->user_id,
//                            'currency' => $order->currency,
//                            'coin' => $order->coin,
//                            'tradeType' => $order->trade_type,
//                            'type' => $order->type,
//                            'price' => BigNumber::round(BigNumber::new($order->price)->div($pricePrecision), BigNumber::ROUND_MODE_HALF_UP, 0),
//                            'quantity' => BigNumber::round(BigNumber::new($order->quantity)->sub(BigNumber::new($order->executed_quantity))->div($quantityPrecision), BigNumber::ROUND_MODE_HALF_UP, 0),
//                        ];
//                    }
//
//                    //send kafka
//                    Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_INIT, $dataOrders);
//                }
//
//            }

			$sendOrder = false;
            $orders = DB::table('orders')
                ->whereIn('status', [Consts::ORDER_STATUS_PENDING, Consts::ORDER_STATUS_EXECUTING])
                ->orderBy('updated_at', 'asc')
                ->select(['id', 'user_id', 'quantity', 'executed_quantity', 'type', 'stop_condition', 'trade_type', 'base_price', 'price', 'currency', 'coin', 'status', 'updated_at'])
                ->chunkById(500, function($items) use($pairs, &$sendOrder) {
                    $dataOrders = [
                        'type' => "order",
                        'data' => []
                    ];
                    foreach ($items as $order) {
                        $keySymbol = Utils::getKeySymbolSpot($order->currency, $order->coin);
                        $pairInfo = isset($pairs[$keySymbol]) ? $pairs[$keySymbol] : null;
                        $pricePrecision = $pairInfo ? $pairInfo['price_precision'] : 1;
                        $quantityPrecision = $pairInfo ? $pairInfo['quantity_precision'] : 1;
                        $dataOrders['data'][] = [
                            'orderId' => $order->id,
                            'userId' => $order->user_id,
                            'currency' => $order->currency,
                            'coin' => $order->coin,
                            'tradeType' => $order->trade_type,
                            'type' => $order->type,
                            'price' => BigNumber::round(BigNumber::new($order->price)->div($pricePrecision), BigNumber::ROUND_MODE_HALF_UP, 0),
                            'quantity' => BigNumber::round(BigNumber::new($order->quantity)->sub(BigNumber::new($order->executed_quantity))->div($quantityPrecision), BigNumber::ROUND_MODE_HALF_UP, 0),
                        ];
                    }

                    //send kafka
                    Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_BE_INIT, $dataOrders);
                    $sendOrder = true;
                });

            if ($sendOrder) {
				Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_BE_INIT, ['type' => "order-end"]);
			}
        }

		if ($type == 'all') {
			//Utils::kafkaProducerME(Consts::KAFKA_TOPIC_ME_INIT, ['type' => "init-complete"]);
		}*/

        $process = Process::on('master')->firstOrCreate(['key' => 'spot_send_init_kafka_matching_engine']);
        $process->processed_id = 0;
        $process->save();
    }
}
