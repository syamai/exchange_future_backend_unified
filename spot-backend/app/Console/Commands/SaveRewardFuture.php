<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\VoucherService;
use App\Utils;
use Illuminate\Console\Command;
use Junges\Kafka\Contracts\KafkaConsumerMessage;

class SaveRewardFuture extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'consumer:save-reward';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command Save Info Reward';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $topic = Consts::TOPIC_CONSUMER_REWARD_FUTURE;
        $handler = new HandlerRewardFuture();
        Utils::kafkaConsumer($topic, $handler);
    }
}

class HandlerRewardFuture
{
    public function __invoke(KafkaConsumerMessage $message)
    {
        $data = Utils::convertDataKafkaRewardFuture($message);

        // update 2 users
        foreach ($data['data'] as $item){
            $arr = [
                'user_id' => $item['userId'],
                'balance' => $item['volume'],
                'symbol' => $item['symbol']
            ];

            app(VoucherService::class)->addBalanceVoucherFuture($arr);
        }
    }
}
