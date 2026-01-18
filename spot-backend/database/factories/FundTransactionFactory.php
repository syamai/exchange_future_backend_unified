<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FundTransactionFactory extends Factory
{
    protected $model = \MAM\Models\FundTransaction::class;

    public function definition()
    {
        return [
            'join_fund_id' => rand(1, 9),
            'investor_id' => rand(1, 9),
            'amount' => rand(1111, 9999)
        ];
    }
}
