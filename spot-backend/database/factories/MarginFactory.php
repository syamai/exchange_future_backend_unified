<?php

namespace Database\Factories;

use App\Models\Index;
use App\Models\Instrument;
use Faker\Generator as Faker;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

class MarginFactory extends Factory
{
    protected $model = Instrument::class;

    public function definition()
    {
        return [
            'symbol' => 'XBTU14',
            'state' => 'Open',
            'type' => rand(0, 10),
            'expiry' => Carbon::now(),
            'base_underlying' => 'XBT',
            'quote_currency' => 'USD',
            'underlying_symbol' => 'XBT=',
            'settle_currency' => 'XBt',
            'init_margin' => rand(1, 1000),
            'maint_margin' => rand(1, 1000),
            'deleverageable' => true,
            'maker_fee' => rand(1, 10),
            'taker_fee' => rand(1, 10),
            'settlement_fee' => rand(1, 10),
            'has_liquidity' => 1,
            'settled_price' => rand(1, 10),
            'reference_indices' => 'BMEX',
            'funding_base_indices' => '',
            'funding_quote_indices' => '',
            'funding_premium_indices' => '',
            'funding_interval' => 8,
            'tick_size' => rand(1, 1000),
            'max_price' => rand(1, 1000),
            'max_order_qty' => rand(1, 1000),
            'multiplier' => random_int(-1, 1),
            'option_strike_price' => rand(1, 1000),
            'option_ko_price' => rand(1, 1000),
            'created_at' => Carbon::now()
        ];
    }
}
