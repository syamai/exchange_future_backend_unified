<?php

namespace Database\Factories;

use App\Models\Index;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class IndexFactory extends Factory
{
    protected $model = Index::class;

    public function definition()
    {
        return [
            'symbol' => 'XBTU14',
            'value' => rand(1, 1000),
            'created_at' => Carbon::now()->subDays(rand(0, 300))->format('Y-m-d H:i:s')
        ];
    }
}
