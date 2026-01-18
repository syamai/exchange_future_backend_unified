<?php
/*
 * Copyright (c) 2022.
 * Simon.Tran
 */

namespace App\Http\Services\PriceServices;

use App\Models\Price;

/**
 *  All method for Current and Coin on master
 */
class PriceMasterService extends PriceBaseService
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
     * @param string $orderBy
     *
     * @return Price|null
     */
    public function getPrice(string $currency, string $coin, string $orderBy): ?Price
    {
        $keyName = $this->getKeyName($currency, $coin);

        if ($this->isExistInMemory($keyName)) {
            return static::$prices[ $keyName ];
        }

        /** @var Price $price */
        $price = Price::on('master')->where('currency', $currency)
            ->where('coin', $coin)
            ->orderBy('created_at', $orderBy)
            ->first();

        static::$prices[ $keyName ] = $price;

        return $price;
    }


    /**
     * @param string $currency
     * @param string $coin
     *
     * @return string
     */
    protected function getKeyName(string $currency, string $coin): string
    {
        return $currency . $coin;
    }
}
