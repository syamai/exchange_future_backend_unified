<?php

namespace App\Console\Commands;

use App\Consts;
use App\Utils;
use Illuminate\Console\Command;

class SendAmountVoucherProducer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'producer:send-reward-future {userId} {amount} {currency}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send amount voucher for future balance';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $topic = Consts::TOPIC_PRODUCER_REWARD_FUTURE;
        $message = [
            'userId' => $this->argument('userId'),
            'amount' => $this->argument('amount'),
            'asset' => strtoupper($this->argument('currency')),
            'type' => strtoupper(Consts::TYPE_REWARD)
        ];

        Utils::kafkaProducer($topic, $message);
    }
}
