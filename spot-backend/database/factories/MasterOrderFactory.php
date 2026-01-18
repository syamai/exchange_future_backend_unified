<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MAM\Models\MasterOrder;

class MasterOrderFactory extends Factory
{
    protected $model = MasterOrder::class;

    public function definition()
    {
        return [
            'original_id' => rand(0, 9),
            'user_id' => rand(0, 9),
            'instrument_symbol' => rand(0, 9),
            'side' => rand(0, 9),
            'type' => rand(0, 9),
            'quantity' => rand(0, 9),
            'price' => rand(11111, 99999),
            'stop_price' => rand(11111, 99999),
            'stop_condition' => rand(11111, 99999),
            'trigger' => rand(0, 9),
            'fee' => rand(0, 9),
            'status' => rand(0, 2),
        ];
    }
}
