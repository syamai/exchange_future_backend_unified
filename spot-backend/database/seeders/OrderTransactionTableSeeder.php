<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Utils;

class OrderTransactionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        for ($i = 0; $i < 1000; $i++) {
            $this->createOrderTransaction();
        }
    }

    private function createOrderTransaction()
    {
        DB::table('order_transactions')->insert([
            'buy_order_id' => rand(1, 100),
            'sell_order_id' => rand(1, 100),
            'price' => rand(100, 1000) + rand(1, 1000) / 1000,
            'status' => rand(1, 5),
            'created_at' => Utils::currentMilliseconds()
        ]);
    }
}
