<?php
/*
 * Copyright (c) 2022.
 * Simon.Tran
 */

namespace App\Http\Services\PriceServices;

use App\Models\Price;
use Illuminate\Support\Facades\DB;

/**
 *  All method for Current and Coin will be here
 */
class PriceSelectByTimeService extends PriceBaseService
{

    /**
     * @var array
     */
    protected static array $prices = [];

    /**
     * @return array
     */
    protected function prices(): array
    {
        return static::$prices;
    }

    /**
     * @param string $currency
     * @param string $coin
     * @param int    $time
     *
     * @return Price|null
     */
    public function getPrice(string $currency, string $coin, int $time): ?Price
    {
        $keyName = $this->getKeyName($currency, $coin, $time);

        if ($this->isExistInMemory($keyName)) {
            return static::$prices[ $keyName ];
        }

        /** @var Price $price */
        $price = Price::where('currency', $currency)
            ->where('coin', $coin)
            ->where('created_at', '>=', $time)
            ->select(DB::raw('0 as current_price, 0 as changed_percent, max(price) as max_price, min(price) as min_price, sum(quantity) as volume, sum(amount) as quote_volume'))
            ->first();

        static::$prices[ $keyName ] = $price;

        return $price;
    }

    /**
     * @param string $currency
     * @param string $coin
     * @param int    $time
     *
     * @return string
     */
    protected function getKeyName(string $currency, string $coin, int $time): string
    {
        return $currency . $coin . $time;
    }
}
