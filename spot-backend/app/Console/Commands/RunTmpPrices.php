<?php

namespace App\Console\Commands;

use App\Consts;
use App\Models\Price;
use App\Models\SpotCommands;
use App\Models\TmpPrice;
use App\Models\TotalPrice;
use App\Utils;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunTmpPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'price:tmp';

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
        //SpotCommands::where('created_at', '<', Carbon::now()->subHour(5))->where('status', '!=', 'pending')->delete();
        $maxCommandId = SpotCommands::where('created_at', '<', Carbon::now()->subHour(5))->where('status', '!=', 'pending')->max('id');
        if ($maxCommandId) {
            SpotCommands::where('id', '<=', $maxCommandId)->where('status', '!=', 'pending')->delete();
        }

        $maxId = TmpPrice::max('id');
        if (!$maxId) {
            $maxId = Price::where('created_at', '>=', Utils::previous24hInMillis())->min('id');
            //$maxId -=1;
        }


        if ($maxId) {
            $sql = "insert into tmp_prices (id, currency, coin, price, quantity, amount, is_crawled, created_at) select id, currency, coin, price, quantity, amount, is_crawled, created_at from prices where id > {$maxId}";
            DB::insert($sql);
        }

        TmpPrice::where('created_at', '<', Utils::previous24hInMillis())->delete();

        $pairs = TmpPrice::select(['currency', 'coin'])
            ->groupBy("currency", "coin")
            ->get();

        foreach ($pairs as $coins) {
            $totalPrice = TotalPrice::where([
                'currency' => $coins->currency,
                'coin' => $coins->coin
            ])->first();
            if (!$totalPrice) {
                TotalPrice::create([
                    'currency' => $coins->currency,
                    'coin' => $coins->coin,
                    'max_price' => 0,
                    'min_price' => 0,
                    'volume' => 0,
                    'quote_volume' => 0
                ]);
            }

        }

        $totalPrices = TotalPrice::all();
        foreach ($totalPrices as $totalPrice) {
            $result = TmpPrice::select(DB::raw('currency, coin, max(price) as max_price, min(price) as min_price, sum(quantity) as volume, sum(amount) as quote_volume'))
                ->where(
                    [
                        "currency" => $totalPrice->currency,
                        "coin" => $totalPrice->coin
                    ])
                ->first();
            if ($result) {
                $totalPrice->update([
                    'max_price' => $result->max_price ?? 0,
                    'min_price' => $result->min_price ?? 0,
                    'volume' => $result->volume ?? 0,
                    'quote_volume' => $result->quote_volume ?? 0
                ]);
            }
        }

        /*$result = TmpPrice::select(DB::raw('currency, coin, 0 as current_price, 0 as changed_percent, max(price) as max_price, min(price) as min_price, sum(quantity) as volume, sum(amount) as quote_volume'))
            ->groupBy("currency", "coin")
            ->get();
        foreach ($result as $r) {
            $totalPrice = TotalPrice::where([
                'currency' => $r->currency,
                'coin' => $r->coin
            ])->first();
            if ($totalPrice) {


            } else {
                TotalPrice::create([
                    'currency' => $r->currency,
                    'coin' => $r->coin,
                    'max_price' => $r->max_price,
                    'min_price' => $r->min_price,
                    'volume' => $r->volume,
                    'quote_volume' => $r->quote_volume
                ]);
            }
        }*/


    }
}
