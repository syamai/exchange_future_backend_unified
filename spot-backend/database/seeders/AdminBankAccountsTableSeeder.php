<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminBankAccountsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('admin_bank_accounts')->truncate();
        DB::table('admin_bank_accounts')->insert([
            [
                'id' => 1,
                'bank_name' => 'Techcombank',
                'bank_branch' => 'Techcombank Hoang Cau',
                'account_name' => 'Admin Amanpuri',
                'account_no' => '111111111111'
            ]
        ]);
    }
}
