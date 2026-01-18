<?php

namespace App\Exceptions;

use App\Exceptions\MarginException;
use App\Consts;

class ReduceOnlyException extends MarginException
{
    public $type;
    public $orderId;

    public function __construct($type, $orderId)
    {
        $message = 'This reduce only order can not match';
        $this->type = $type;
        $this->orderId = $orderId;
        parent::__construct($message);
    }
}
