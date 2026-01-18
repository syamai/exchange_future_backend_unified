<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Events\PricesUpdated;
use Illuminate\Support\Facades\DB;

class SendTestPricesUpdatedEvent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prices:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send test PricesUpdated event';



    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        echo "sending test PricesUpdated event\n";
        $data = DB::table('prices')->select('currency', 'price')->get()->keyBy('currency');
        event(new PricesUpdated($data));
    }
}
