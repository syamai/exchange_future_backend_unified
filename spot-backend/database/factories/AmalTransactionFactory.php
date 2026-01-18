<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AmalTransactionFactory extends Factory
{
    protected $model = \App\Models\AmalTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_id' => rand(1, 10),
            'amount' => rand(1, 10),
            'currency' => 'ETH',
            'total' => rand(1, 10),
            'bonus' => rand(1, 10),
            'price' => rand(1, 10),
            'price_bonus' => rand(1, 10),
            'payment' => rand(1, 10),
        ];
    }
}
