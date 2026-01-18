<?php

namespace Database\Factories;

use App\Models\User;
use Faker\Generator as Faker;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/
class UserFactory extends Factory
{
    protected $model = User::class;
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        static $password;

        return [
            'name' => fake()->name,
            'email' => fake()->unique(true)->email,
            'password' => $password ?: $password = bcrypt('123123'),
            'remember_token' => Str::random(10),
            'type' => 'bot'
        ];
    }
}

//fake()->defineAs(App\User::class, 'trading@gmail.com', function (Faker fake()) {
//    static $password;
//
//    return [
//        'id' => 1000,
//        'name' => fake()->name,
//        'email' => 'trading@gmail.com',
//        'security_level' => 4,
//        'password' => $password ?: $password = bcrypt('123123'),
//        'remember_token' => Str::random(10),
//        'type' => 'bot'
//    ];
//});
