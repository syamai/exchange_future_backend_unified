<?php

namespace App\Exceptions;

use App\Exceptions\MarginException;

class PositionNotFoundException extends MarginException
{
    public $positionId;

    public function __construct($positionId)
    {
        $message = 'Position does not exist';
        $this->positionId = $positionId;
        parent::__construct($message);
    }
}
