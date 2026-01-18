<?php

namespace App\Exceptions;

use App\Exceptions\MarginException;
use App\Consts;

class CannotOpenOrderException extends MarginException
{
    public $type;

    public function __construct($type)
    {
        if ($type == Consts::MARGIN_EXCEPTION_REDUCE) {
            $message = 'Cannot open reduce only order';
        } elseif ($type == Consts::MARGIN_EXCEPTION_LIQUID) {
            $message = 'Cannot open order because it can liquid immediately';
        } else {
            $message = '';
        }
        $this->type = $type;
        parent::__construct($message);
    }
}
