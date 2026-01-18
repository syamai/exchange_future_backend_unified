<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Models\CircuitBreakerSetting;

class CircuitBreakerSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        CircuitBreakerSetting::truncate();
        CircuitBreakerSetting::create([
            'range_listen_time' => 1,   // Monitor in 1 hour
            'circuit_breaker_percent' => 10,  // 10%
            'block_time' => 1,  // Block 1h
        ]);
    }
}
