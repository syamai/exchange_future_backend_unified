<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountriesSeeder extends Seeder
{
    public function run()
    {
        $tableName = "countries";
        $countries = @json_decode(file_get_contents(realpath(__DIR__ . '/dataset/countries.json'), true));
        DB::table($tableName)->delete();
        foreach ($countries as $countryId => $country) {
            DB::table($tableName)->insert(array(
                'id' => $countryId,
                'country_code' =>$country->iso_3166_2 ?? null,
                'name' => $country->name ?? null,
                'currency' => $country->currency ?? null,
                'currency_code' => $country->currency_code ?? null,
                'iso_code' => $country->iso_3166_3,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ));
        }
    }
}