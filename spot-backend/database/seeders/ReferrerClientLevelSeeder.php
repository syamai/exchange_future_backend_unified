<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferrerClientLevelSeeder extends Seeder
{
    /**
     * Seeds referrer client level.
     *
     * @return void
     */
    public function run()
    {
        $levels = config('constants.referrer_client_level');

        $rows = [];

        foreach ($levels as $level => $data) {
            $rows[] = [
                'level'      => $level,
                'trade_min' => $data['tradeRange']['min'],
                'volume'     => $data['volume'],
                'rate'       => $data['rate'],
                'label'      => $data['label'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        DB::table('referrer_client_levels')->insert($rows);
    }
}
