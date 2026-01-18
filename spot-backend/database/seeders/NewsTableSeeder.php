<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class NewsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('news')->truncate();
        for ($i = 0; $i < 25; $i++) {
            $this->createNew($i);
        }
    }

    private function createNew($i)
    {
        $faker = Faker\Factory::create();
        DB::table('news')->insert([
            'article_id' => $faker->numberBetween(1111111111, 9999999999),
            'title' => 'News ' . $i,
            'created_at' => $faker->dateTimeBetween('-100 days', '+0 days'),
            'updated_at' => $faker->dateTimeBetween('-100 days', '+0 days'),
            'url' => $faker->url,
        ]);
    }
}
