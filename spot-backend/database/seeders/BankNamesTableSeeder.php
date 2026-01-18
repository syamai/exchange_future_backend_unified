<?php

namespace Database\Seeders;

use App\Consts;
use App\Jobs\CreateUserAccounts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use App\Utils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BankNamesTableSeeder extends Seeder
{
    public function run()
    {
        $tableName = "bank_names";
        $bankNames = @json_decode(file_get_contents(realpath(__DIR__ . '/dataset/bank_krw.json'), true));
        DB::table($tableName)->delete();
        foreach ($bankNames as $bankNameId => $bankName) {
            DB::table($tableName)->insert(array(
                'id' => $bankNameId,
                'code' =>$bankName->bankCode,
                'name' =>$bankName->bankName,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ));
        }
    }
}
