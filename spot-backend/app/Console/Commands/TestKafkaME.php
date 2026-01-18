<?php

namespace App\Console\Commands;

use App\Utils;
use Illuminate\Console\Command;
use Junges\Kafka\Contracts\KafkaConsumerMessage;
use Junges\Kafka\Facades\Kafka;

class TestKafkaME extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kafka_me:run';

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
        $topic = 'events_me';
        $handler = new HandlerME();
        Utils::kafkaConsumerME($topic, $handler);
    }
}

class HandlerME
{
    public function __invoke(KafkaConsumerMessage $message)
    {
        $data = Utils::convertDataKafka($message);

        $user = $data['userId'];
        $volume = $data['volume'];

        echo "\nTime: ".time();
        echo "\nUserId: ".$user;
        echo "\nVolume: ".$volume;
    }
}
