<?php

namespace Database\Factories;

use App\Models\Settlement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettlementFactory extends Factory
{
    protected $model = Settlement::class;

    public function definition()
    {
        return [
            'symbol' => 'XBU24H',
            'settled_price' => rand(1, 1000),
            'option_strike_price' => rand(1, 1000),
            'option_underlying_price' => rand(1, 1000),
            'tax_base' => rand(1, 1000),
            'tax_rate' => rand(1, 1000),
            'created_at' => Carbon::now()
        ];
    }
}
