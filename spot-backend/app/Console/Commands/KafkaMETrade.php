<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\OrderService;
use App\Jobs\ProcessOrderMETrade;
use App\Models\Order;
use App\Utils;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\KafkaConsumerMessage;

class KafkaMETrade extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kafka_me:trade';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command receive data trade from ME';

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

        $topic = Consts::KAFKA_TOPIC_ME_TRADE;
        $handler = new HandlerMETrade();
        Utils::kafkaConsumerME($topic, $handler);

        return Command::SUCCESS;
    }

}

class HandlerMETrade {

    public function __invoke(KafkaConsumerMessage $message)
    {

        $data = Utils::convertDataKafka($message);
        try {
//            $orderService = app(OrderService::class);
//            //$buyId = $data['buyer_id'];
//            //$sellerId = $data['seller_id'];
//            $quantity = $data['quantity'];
//            $price = $data['price'];
//            $transactionType = $data['transaction_type'];
//            //$createdAt = $data['created_at'];
//            $buyOrderId = $data['buy_order_id'];
//            $sellOrderId = $data['sell_order_id'];
//            $buyFee = isset($data['buy_fee']) ? $data['buy_fee'] : 0;
//            $sellFee = isset($data['sell_fee']) ? $data['sell_fee'] : 0;
//            //$status = $data['status'];
//
//            $isBuyerMaker = $transactionType != Consts::ORDER_TRADE_TYPE_BUY;
//
//            $buyOrder = Order::on('master')->where('id', $buyOrderId)->lockForUpdate()->first();
//            $sellOrder = Order::on('master')->where('id', $sellOrderId)->lockForUpdate()->first();
//
//            if (!$buyOrder->canMatching() || !$sellOrder->canMatching()) {
//                if (!$buyOrder || !$buyOrder->canMatching()) {
//                    throw new Exception("Order not found: " . $buyOrderId);
//                }
//                if (!$sellOrder || !$sellOrder->canMatching()) {
//                    throw new Exception("Order not found: " . $sellOrderId);
//                }
//            }
//
//            $orderService->matchEngineOrders($buyOrder, $sellOrder, $price, $quantity, $buyFee, $sellFee, $isBuyerMaker);
            ProcessOrderMETrade::onNewOrderTransactionCreated($data);


            //throw new Exception('This application must be run on the command line.');
        } catch (Exception $e) {
            Log::error('Matching Engine Trade. Failed to create complete_transactions: '. json_encode($data));
            Log::error($e);
            throw $e;
        }

    }
}
