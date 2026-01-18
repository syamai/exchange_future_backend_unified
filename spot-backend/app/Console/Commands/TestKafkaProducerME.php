<?php

namespace App\Console\Commands;

use App\Utils;
use Illuminate\Console\Command;

class TestKafkaProducerME extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kafka_me:producer';

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
        $timeStart = microtime(true);
        echo "\nStart: ".$timeStart;
        $message = ["userId" => 1, "volume" => 2];

        $topic = 'events_me';

        Utils::kafkaProducerME($topic, $message);
        $timeEnd = microtime(true);
        echo "\nend udpate db:" . ($timeEnd - $timeStart);
    }
}
