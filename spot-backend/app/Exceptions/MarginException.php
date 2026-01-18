<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class MarginException extends HttpException
{
    public function __construct($message)
    {
        parent::__construct(422, $message);
    }
}
