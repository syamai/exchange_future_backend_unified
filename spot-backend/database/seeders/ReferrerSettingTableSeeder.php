<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ReferrerSetting;
use App\Consts;

class ReferrerSettingTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        ReferrerSetting::truncate();
        $this->createNew();
    }
    private function createNew()
    {
        ReferrerSetting::create([
            'enable' => true,
            'number_of_levels' => Consts::NUMBER_OF_LEVELS,
            'refund_rate' => Consts::REFUND_RATE,
            'refund_percent_at_level_1' => 40,
            'refund_percent_at_level_2' => 30,
            'refund_percent_at_level_3' => 15,
            'refund_percent_at_level_4' => 10,
            'refund_percent_at_level_5' => 5,
        ]);
    }
}
