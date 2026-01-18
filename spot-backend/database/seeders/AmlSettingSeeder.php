<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AmlSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\AmalSetting::truncate();
        \App\Models\AmalSetting::factory()->count(1)->create();
    }
}
