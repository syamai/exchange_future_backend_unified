<?php

namespace App\Console\Commands;

use App\Utils;
use Illuminate\Console\Command;

class TestKafkaProducer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kafka:producer';

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
        $message = ["userId" => 1, "volume" => 2];

        $topic = 'events';

        Utils::kafkaProducer($topic, $message);
    }
}
