<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoinMarketCapTicketsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('coin_market_cap_tickers')->truncate();
        $data = json_decode(file_get_contents(base_path().'/database/seeders/dataset/coin_market_cap_tickers.json'));
        $rows = [];
        foreach ($data as $row) {
            $rows[] = array(
                'name' => $row->name,
                'symbol' => $row->symbol,
                'rank' => $row->rank,
                'price_usd' => $row->price_usd,
                'price_btc' => $row->price_btc,
                '24h_volume_usd' => $row->{'24h_volume_usd'},
                'market_cap_usd' => $row->market_cap_usd,
                'available_supply' => $row->available_supply,
                'total_supply' => $row->total_supply,
                'max_supply' => $row->max_supply,
                'percent_change_1h' => $row->percent_change_1h,
                'percent_change_24h' => $row->percent_change_24h,
                'percent_change_7d' => $row->percent_change_7d,
                'last_updated' => Carbon::createFromTimestamp($row->last_updated),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            );
        }
        DB::table('coin_market_cap_tickers')->insert($rows);
    }
}
