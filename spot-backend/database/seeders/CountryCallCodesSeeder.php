<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountryCallCodesSeeder extends Seeder
{
    public function run()
    {
        $tableName = "countries";
        $countries = @json_decode(file_get_contents(realpath(__DIR__ . '/dataset/countryCallCodes.json'), true));
        foreach ($countries as $countryId => $country) {
            DB::table($tableName)->where('country_code', $country->code)
                ->update(['calling_code' => $country->dial_code]);
        }
    }
}