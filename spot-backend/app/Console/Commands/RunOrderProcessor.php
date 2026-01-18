<?php

namespace App\Console\Commands;

use App\Consts;
use App\Utils;
use App\Jobs\ProcessOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunOrderProcessor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:process {currency} {coin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $matchingJavaAllow = env("MATCHING_JAVA_ALLOW", false);
        if ($matchingJavaAllow) {
            return Command::SUCCESS;
        }

        $currency = $this->argument('currency');
        $coin = $this->argument('coin');
        ProcessOrder::dispatch($currency, $coin)->onQueue(Consts::QUEUE_PROCESS_ORDER)->onConnection(Consts::CONNECTION_SOCKET);
    }
}
