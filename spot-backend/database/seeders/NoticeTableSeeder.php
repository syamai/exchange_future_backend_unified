<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Utils;

class NoticeTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('notices')->truncate();
        for ($i = 0; $i < 20; $i++) {
            $this->createNotice();
        }
    }

    private function createNotice()
    {
        $faker = Faker\Factory::create();
        DB::table('notices')->insert([
            'title' => $faker->sentence(10, true),
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now(),
            'banner_url' => 'images/mobile/landing/body-image.png',
            'support_url' => $faker->url,
            'started_at' => Utils::currentMilliseconds(),
            'ended_at' => Utils::currentMilliseconds(),
        ]);
    }
}
