<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AllocationSettingFactory extends Factory
{
    protected $model = \MAM\Models\AllocationSetting::class;

    public function definition()
    {
        return [
            'user_id' => rand(1, 10),
            'setting' => rand(0, 2)
        ];
    }
}
