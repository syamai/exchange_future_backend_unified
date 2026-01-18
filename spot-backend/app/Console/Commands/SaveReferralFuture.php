<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SaveReferralFuture extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'consumer:save-referral';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command Save Info Referral';

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
