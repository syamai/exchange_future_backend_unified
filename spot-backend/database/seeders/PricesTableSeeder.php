<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PricesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('prices')->truncate();
        $prices = [
            'btc' => 7380000,
            'bch' => 50400,
            'etc' => 12060,
            'eth' => 347250,
            'ltc' => 63500,
            'xrp' => 204,
            'dash' => 9700,
        ];
        foreach ($prices as $coin => $price) {
            $this->createPrice('usd', $coin, $price);
        }
        $this->createPrices('btc', $prices);
        $this->createPrices('eth', $prices);
    }

    private function createPrices($baseCoin, $prices)
    {
        $coins = ['btc', 'bch', 'etc', 'eth', 'ltc', 'xrp', 'dash'];
        $basePrice = $prices[$baseCoin];
        foreach ($coins as $coin) {
            if ($coin != $baseCoin) {
                $this->createPrice($baseCoin, $coin, $prices[$coin] / $basePrice);
            }
        }
    }

    private function createPrice($currency, $coin, $price)
    {
        $currentPrice = $price;

        for ($i = 1; $i <= 1; $i++) {
            // random -0,1 to 0,1 Not 0
            $rand = rand(-10, 10) / 1000;

            if ($rand != 0) {
                $currentPrice += $currentPrice * $rand;
            }

            // for close price
            DB::table('prices')->insert([
                'currency'   => $currency,
                'coin'       => $coin,
                'price'      => $currentPrice + ($currentPrice * $rand),
                'quantity'   => 100,
                'amount'     => 100 * ($currentPrice + ($currentPrice * $rand)),
                'created_at' => \Carbon\Carbon::now()->subDays($i)->addHours(5)->timestamp * 1000
            ]);
            // for open price
            DB::table('prices')->insert([
                'currency'   => $currency,
                'coin'       => $coin,
                'price'      => $currentPrice - ($currentPrice * $rand),
                'amount'     => 100 * ($currentPrice + ($currentPrice * $rand)),
                'quantity'   => 100,
                'created_at' => \Carbon\Carbon::now()->subDays($i)->addHours(1)->timestamp * 1000
            ]);
            // for max price
            DB::table('prices')->insert([
                'currency'   => $currency,
                'coin'       => $coin,
                'price'      => $currentPrice / 100 * 102,
                'quantity'   => 100,
                'amount'     => 100 * ($currentPrice / 100 * 102),
                'created_at' => \Carbon\Carbon::now()->subDays($i)->addHours(2)->timestamp * 1000
            ]);
            // for min price
            DB::table('prices')->insert([
                'currency'   => $currency,
                'coin'       => $coin,
                'price'      => $currentPrice / 100 * 98,
                'quantity'   => 100,
                'amount'     => 100 * ($currentPrice / 100 * 102),
                'created_at' => \Carbon\Carbon::now()->subDays($i)->addHours(3)->timestamp * 1000
            ]);
        }
    }
}
