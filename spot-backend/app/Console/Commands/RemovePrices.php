<?php

namespace App\Console\Commands;

use App\Consts;
use App\Models\Price;
use App\Models\TmpPrice;
use App\Models\TotalPrice;
use App\Utils;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemovePrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'price:remove';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command copy data price';


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $removePriceData = env("REMOVE_PRICE_DATA", true);
        if (!$removePriceData) {
            return Command::SUCCESS;
        }
        Price::where('is_market', 0)->where('created_at', '<', Carbon::now()->subDays(10)->timestamp * 1000)->delete();
    }
}
