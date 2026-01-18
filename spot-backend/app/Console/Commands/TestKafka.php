<?php

namespace App\Console\Commands;

use App\Utils;
use Illuminate\Console\Command;
use Junges\Kafka\Contracts\KafkaConsumerMessage;
use Junges\Kafka\Facades\Kafka;

class TestKafka extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kafka:run';

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
        $topic = 'events';
        $handler = new Handler();
        Utils::kafkaConsumer($topic, $handler);
    }
}

class Handler
{
    public function __invoke(KafkaConsumerMessage $message)
    {
        $data = Utils::convertDataKafka($message);

        $user = $data['code'];
        $volume = $data['data']['volume'];

        echo $user;
        echo $volume;
    }
}
