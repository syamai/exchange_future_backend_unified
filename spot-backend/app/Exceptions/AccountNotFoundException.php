<?php

namespace App\Exceptions;

use App\Exceptions\MarginException;

class AccountNotFoundException extends MarginException
{
    public $accountId;

    public function __construct($accountId)
    {
        $message = 'Account does not exist';
        $this->accountId = $accountId;
        parent::__construct($message);
    }
}
