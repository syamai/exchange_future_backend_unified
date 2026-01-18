<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MarketFeeSettingTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('market_fee_setting')->truncate();
        $this->createNew();
    }

    private function createNew()
    {
        $data = [
            ['btc', 'eth'],
            ['btc', 'ltc'],
            ['btc', 'amal'],
            ['btc', 'bch'],
            ['btc', 'xrp'],
            ['btc', 'eos'],
            ['btc', 'ada'],
            ['usd', 'btc'],
            ['usd', 'eth'],
            ['usd', 'amal'],
            ['usd', 'bch'],
            ['usd', 'ltc'],
            ['usd', 'xrp'],
            ['usd', 'eos'],
            ['usd', 'ada'],
            ['usd', 'usdt'],
            ['eth', 'btc'],
            ['eth', 'amal'],
            ['eth', 'bch'],
            ['eth', 'ltc'],
            ['eth', 'xrp'],
            ['eth', 'eos'],
            ['eth', 'ada'],
            ['usdt', 'btc'],
            ['usdt', 'eth'],
            ['usdt', 'amal'],
            ['usdt', 'bch'],
            ['usdt', 'ltc'],
            ['usdt', 'xrp'],
            ['usdt', 'eos'],
            ['usdt', 'ada'],
        ];

        foreach ($data as $coins) {
            DB::table('market_fee_setting')->insert([
                'currency' => $coins[0],
                'coin' => $coins[1],
                'fee_taker' => 0.15,  // 0.15%
                'fee_maker' => 0.15,  // 0.15%
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
