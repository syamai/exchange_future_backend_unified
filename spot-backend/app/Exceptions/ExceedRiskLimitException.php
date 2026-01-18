<?php

namespace App\Exceptions;

use App\Exceptions\MarginException;
use App\Consts;

class ExceedRiskLimitException extends MarginException
{
    public $riskValue;

    public function __construct($riskValue)
    {
        $message = 'Exceed risk limit';
        $this->riskValue = $riskValue;
        parent::__construct($message);
    }
}
