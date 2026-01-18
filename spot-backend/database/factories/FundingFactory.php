<?php

namespace Database\Factories;

use App\Models\Funding;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class FundingFactory extends Factory
{
    protected $model = Funding::class;

    public function definition()
    {
        return [
            'symbol' => 'XBU24H',
            'funding_interval' => '8h',
            'funding_rate' => rand(1, 1000),
            'funding_rate_daily' => rand(1, 1000),
            'created_at' => Carbon::now()
        ];
    }
}
