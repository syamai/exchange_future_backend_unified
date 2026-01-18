<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AmlTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\AmalTransaction::truncate();
        \App\Models\AmalTransaction::factory()->count(100)->create();
    }
}
