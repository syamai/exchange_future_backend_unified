<?php

namespace App\Exceptions;

use Exception;

class UnregisteredSessionException extends Exception
{
    public function __construct()
    {
        parent::__construct(__('messages.sesstion_terminated'));
    }
}
