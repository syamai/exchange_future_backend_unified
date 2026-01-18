<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\TransferService;
use App\Utils;
use Illuminate\Console\Command;
use Junges\Kafka\Contracts\KafkaConsumerMessage;

class ConsumerTransferFuture extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'consumer:transfer-future';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'consumer transfer future';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $topic = Consts::TOPIC_CONSUMER_TRANSFER_FUTURE;
        $handler = new HandlerTransferFuture();
        Utils::kafkaConsumer($topic, $handler);
    }
}

class HandlerTransferFuture
{
    public function __invoke(KafkaConsumerMessage $message)
    {
        $data = Utils::convertDataKafkaRewardFuture($message);
        $params = [
            'userId' => $data['userId'],
            'from' => $data['from'],
            'to' => $data['to'],
            'amount' => $data['amount'],
            'asset' => $data['asset']
        ];
        app(TransferService::class)->transferFuture($params);
    }
}
