<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use App\Consts;

class MarginOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
//        $this->createMarginOrders();
    }

    private function createMarginOrders()
    {
        $sideDefs = array('buy', 'sell');
        $statusDefs = array(
            Consts::ORDER_STATUS_STOPPING,
            Consts::ORDER_STATUS_PENDING,
            Consts::ORDER_STATUS_EXECUTED,
            Consts::ORDER_STATUS_CANCELED,
            Consts::ORDER_STATUS_EXECUTING,
            Consts::ORDER_STATUS_REMOVED
        );

        DB::table('margin_orders')->truncate();
        for ($i = 1; $i < 51; $i++) {
            DB::table('margin_orders')->insert([
                'account_id' => $i,
                'instrument_symbol' => $i,
                'side' => $sideDefs[array_rand($sideDefs)],
                'type' => $i,
                'quantity' => $i,
                'price' => $i,
                'lock_price' => $i,
                'remaining' => $i,
                'executed_price' => $i,
                'stop_type' => $i,
                'stop_price' => $i,
                'stop_condition' => $i,
                'trigger' => $i,
                'time_in_force' => $i,
                'fee' => $i,
                'trail_value' => $i,
                'vertex_price' => $i,
                'status' => $statusDefs[array_rand($statusDefs)],
                'is_post_only' => $i,
                'is_hidden' => $i,
                'is_reduce_only' => $i,
                'pair_type' => $i,
                'reference_id' => $i,
                'note' => $i,
                'created_at' => Carbon::now()->timestamp * 1000 + $i,
                'updated_at' => Carbon::now()->timestamp * 1000 + $i,
            ]);
        }
    }
}
