<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SaveTransferFuture extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'consumer:save-transfer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command Save Info Transfer';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        return Command::SUCCESS;
    }
}
