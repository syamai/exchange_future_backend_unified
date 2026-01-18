<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;

class OrdersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //factory(Order::class, 1000)->create();
        $limit = $this->command->ask('LIMIT_SEED', 1000);
        $price = $this->command->ask('PRICE', 57700);

        Order::factory()->setPriceOrder($price)->setTradeType('buy')->count($limit)->create();
        Order::factory()->setPriceOrder($price)->setTradeType('sell')->count($limit)->create();

    }
}
