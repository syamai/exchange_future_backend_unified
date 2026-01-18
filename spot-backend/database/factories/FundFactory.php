<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FundFactory extends Factory
{
    protected $model = \MAM\Models\Fund::class;

    public function definition()
    {
        return [
            'broker_id' => rand(0, 9),
            'is_active' => rand(0, 1),
            'lot' => rand(0, 10),
            'fee' => fake()->randomFloat(4, 0.0001, 0.02),
            'this_month_pnl' => rand(0, 1),
            'last_month_pnl' => rand(0, 1),
            'all_time_pnl' => rand(0, 1)
        ];
    }
}
