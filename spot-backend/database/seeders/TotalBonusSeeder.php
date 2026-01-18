<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DividendTotalBonus;
use App\Http\Services\MasterdataService;

class TotalBonusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('dividend_total_bonus')->truncate();
        DB::table('dividend_total_paid_each_pairs')->truncate();

        $records = MasterdataService::getOneTable('coins');
        foreach ($records as $record) {
            DividendTotalBonus::create([
                'total_bonus' => 0,
                'coin' => $record->coin,
            ]);
        }
    }
}
