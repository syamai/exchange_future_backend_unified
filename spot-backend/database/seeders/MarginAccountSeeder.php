<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use App\Consts;
use Illuminate\Support\Facades\DB;

class MarginAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $insuranceFundEmail = Consts::INSURANCE_FUND_EMAIL;
        DB::statement("INSERT INTO margin_accounts (owner_id) SELECT id FROM users WHERE users.email != '$insuranceFundEmail'");
        DB::statement("INSERT INTO amal_margin_accounts (owner_id) SELECT id FROM users WHERE users.email != '$insuranceFundEmail'");
    }
}
