<?php

namespace Database\Factories;

use App\Models\Order;
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

class OrderFactory extends Factory
{
    protected $model = Order::class;
    public static $tradeType = "";
    public static $price = 0;

    public function definition()
    {
        static $userCount = 2;

        static $priceMap = [
            'btc' => [57700, 57700], // min price, max price
            'bch' => [390000, 510000],
            'eth' => [280000, 420000],
            'xrp' => [280000, 420000],
            'ltc' => [280000, 420000],
            'etc' => [280000, 420000],
            'dash' => [280000, 420000]
        ];

        $tradeType = fake()->randomElement(['buy', 'sell']);
        if (in_array(self::$tradeType, ['buy', 'sell'])) {
            $tradeType = self::$tradeType;
        }

        $currency = fake()->randomElement(['usdt'/*, 'btc', 'eth'*/]);
        $coin = fake()->randomElement(['btc'/*, 'eth', 'bch', 'xrp', 'ltc', 'etc', 'dash'*/]);
        while ($currency == $coin) {
            $coin = fake()->randomElement(['btc', 'eth', 'bch', 'xrp', 'ltc', 'etc', 'dash']);
        }
        $type = fake()->randomElement(['limit'/*, 'market', 'stop_limit', 'stop_market'*/]);
        $quantity = fake()->numberBetween(0.1, 100);
        $quantity = 0.0001;
        $priceRange = $priceMap[$coin];
        $price = fake()->numberBetween($priceRange[0], $priceRange[1]);
        if (self::$price > 0) {
            $price = self::$price;
        }

        $basePrice = null;
        if ($type == 'market' || $type == 'stop_market') {
            if ($tradeType == 'buy') {
                $basePrice = $price * 0.9;
            } else {
                $basePrice = $price * 1.1;
            }
            $price = null;
        }
        $fee = 0;
        if ($tradeType == 'buy') {
            $fee = round(fake()->randomFloat(4, 0.0001, 0.02) * $quantity, 4);
        } else {
            $fee = round(fake()->randomFloat(4, 0.0001, 0.02) * $quantity * $price);
        }
        $status = null;
        if ($type == 'stop_limit' || $type == 'stop_market') {
            $status = fake()->randomElement(['stopping', 'executed']);
        } else {
            $status = fake()->randomElement(['pending'/*, 'executed'*/]);
        }
        $createdAt = round(microtime(true) * 1000) + rand(1, 1000);
        $userId = rand(1, $userCount);
        $email = "bot{$userId}@gmail.com";
        return [
            'user_id' => $userId,
            'email' => $email,
            'trade_type' => $tradeType,
            'currency' => $currency,
            'coin' => $coin,
            'type' => $type,
            'quantity' => $quantity,
            'price' => $price,
            'base_price' => $basePrice,
            'fee' => $fee,
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $createdAt
        ];
    }

    public function setTradeType($tradeType) : self
    {
        self::$tradeType = $tradeType;
        return $this;
    }

    public function setPriceOrder($price) : self
    {
        self::$price = $price;
        return $this;
    }

}
