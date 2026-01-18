<?php

namespace App\Console\Commands;

use Transaction\Models\Transaction;
use Illuminate\Console\Command;
use App\Mail\DepositAlerts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendDepositAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deposit_alerts:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send deposit alerts via email';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $transaction = Transaction::where('amount', '>', 0)->inRandomOrder()->first();
        if (!$transaction) {
            $this->error('There are no records in the transaction table!');
            return false;
        }
        Mail::queue(new DepositAlerts($transaction, $transaction->currency));
        $this->info('Sent mail successfully!');
    }
}
