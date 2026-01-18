<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Models\CircuitBreakerCoinPairSetting;

class CircuitBreakerCoinPairSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        CircuitBreakerCoinPairSetting::truncate();
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

        foreach ($data as $coin) {
            CircuitBreakerCoinPairSetting::create([
                'currency' => $coin[0],
                'coin' => $coin[1],
                'status' => 'enable',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
