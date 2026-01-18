<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\News;
use Illuminate\Support\Facades\DB;

class NewsUserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('news_user')->truncate();
        $users = User::all();
        $news = News::get()->pluck('id');
        foreach ($users as $user) {
            $user->news()->attach($news);
        }
    }
}
