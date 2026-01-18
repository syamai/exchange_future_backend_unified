<?php
/*
 * Copyright (c) 2022.
 * Simon.Tran
 */

namespace App\Http\Services\PriceServices;

/**
 *
 */
abstract class PriceBaseService
{

    /**
     * @return array
     */
    abstract protected function prices(): array;

    /**
     * @param string $currency
     * @param string $coin
     *
     * @return string
     */
    public static function keyName24hChange(string $currency, string $coin): string
    {
        return "Price:$currency:$coin:24hChange";
    }

    /**
     * @param string $keyName
     *
     * @return bool
     */
    protected function isExistInMemory(string $keyName): bool
    {
        $keys = array_keys($this->prices());
        return in_array($keyName, $keys);
    }
}
