<?php

namespace Database\Factories;

use App\Models\Insurance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class InsuranceFactory extends Factory
{
    protected $model = Insurance::class;

    public function definition()
    {
        return [
            'currency' => '',
            'wallet_balance' => rand(1, 1000),
            'created_at' => Carbon::now()
        ];
    }
}
