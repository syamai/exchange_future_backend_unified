<?php

namespace App\Exceptions;

use App\Service\Margin\MarginBigNumber;

class InvalidOrderPriceException extends MarginException
{
    const LIQUIDATION_PRICE = 'liquidation price';
    const BANKRUPT_PRICE = 'bankrupt price';
    const ABOVE = 'above';
    const BELOW = 'below';

    public string $orderId;
    public float $price;

    public function __construct($price, $comparation, $limitPrice, $type)
    {
        $price = MarginBigNumber::new($price)->toString();
        $limitPrice = MarginBigNumber::new($limitPrice)->toString();
        $message = "Your order price of $price is $comparation your current $type of $limitPrice";
        $this->price = $price;
        parent::__construct($message);
    }
}
