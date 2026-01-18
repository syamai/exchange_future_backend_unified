<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MarketFeeSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('market_fee_setting')->truncate();
        $file_path = database_path('seeders/dataset/market_fee_setting.sql');
        DB::unprepared(
            file_get_contents($file_path)
        );
    }

}
