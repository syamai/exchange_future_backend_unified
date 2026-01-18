<?php

namespace App\Exceptions;

use App\Exceptions\MarginException;

class InstrumentNotFoundException extends MarginException
{
    public $symbol;

    public function __construct($symbol)
    {
        $message = 'Instrument does not exist';
        $this->symbol = $symbol;
        parent::__construct($message);
    }
}
