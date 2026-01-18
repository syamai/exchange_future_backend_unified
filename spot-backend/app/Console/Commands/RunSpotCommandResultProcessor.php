<?php

namespace App\Console\Commands;

use App\Consts;
use App\Jobs\ProcessSpotCommandResult;
use Illuminate\Console\Command;

class RunSpotCommandResultProcessor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:process_command_result {type} {currency?} {coin?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command spot command result description';



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

        $type = $this->argument('type');
        $currency = $this->argument('currency') ?? '';
        $coin = $this->argument('coin') ?? '';

        ProcessSpotCommandResult::dispatch($type, $coin, $currency)->onQueue(Consts::QUEUE_PROCESS_ORDER)->onConnection(Consts::CONNECTION_SOCKET);
    }
}
