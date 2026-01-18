<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AmalSettingFactory extends Factory
{
    protected $model = \App\Models\AmalSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'amount' => rand(11111, 99999),
            'usd_price' => rand(6, 10),
            'eth_price' => rand(6, 10),
            'btc_price' => rand(6, 10),

            'usd_price_presenter' => rand(1, 5),
            'eth_price_presenter' => rand(1, 5),
            'btc_price_presenter' => rand(1, 5),

            'usd_price_presentee' => rand(1, 3),
            'eth_price_presentee' => rand(1, 3),
            'btc_price_presentee' => rand(1, 3),
        ];
    }
}
