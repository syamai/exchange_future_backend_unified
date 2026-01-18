<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MarginContractSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */

    private $contracts = array();

    public function run()
    {
        $instruments = json_decode(file_get_contents(base_path() . '/database/seeders/dataset/margin_instruments.json'), true);
        foreach ($instruments as $row) {
            array_push($this->contracts, $row['symbol']);
        }

        DB::table('margin_contract_settings')->truncate();
        $symbols = $this->contracts;
        $data = array();
        for ($i = 0; $i < count($symbols); $i++) {
            array_push($data, array(
                'id' => $i + 1,
                'symbol' => $symbols[$i],
                'is_enable' => 1,
                'is_show_beta_tester' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now()
            ));
        }
        DB::table('margin_contract_settings')->insert($data);
    }
}
