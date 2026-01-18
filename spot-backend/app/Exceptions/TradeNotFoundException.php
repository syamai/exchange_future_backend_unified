<?php

namespace App\Exceptions;

use App\Exceptions\MarginException;

class TradeNotFoundException extends MarginException
{
    public $tradeId;

    public function __construct($tradeId = null)
    {
        $message = 'Trade does not exist';
        $this->tradeId = $tradeId;
        parent::__construct($message);
    }
}
