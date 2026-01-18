<?php

namespace App\Console\Commands;

use App\Consts;
use App\Jobs\ProcessOrderRequestRedis;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunOrderRequestProcessor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:process_request {currency} {coin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command process order request description';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $matchingJavaAllow = env("MATCHING_JAVA_ALLOW", false);
        if (!$matchingJavaAllow) {
            return Command::SUCCESS;
        }

        $currency = $this->argument('currency');
        $coin = $this->argument('coin');
        ProcessOrderRequestRedis::dispatch($currency, $coin)->onQueue(Consts::QUEUE_PROCESS_REQUEST_ORDER)->onConnection(Consts::CONNECTION_SOCKET);
    }
}
