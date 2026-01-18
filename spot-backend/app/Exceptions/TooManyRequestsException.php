<?php

namespace App\Exceptions;

class TooManyRequestsException extends \Exception
{
    private $statusCode;
    private $errorCode;
    private $headers;

    public function __construct($message = 'Too Many Requests.', $headers = [], $params = [])
    {
        $previous = null;
        $this->statusCode = 429; // Too Many Requests
        $this->errorCode = 1003;
        $this->headers = $headers;

        parent::__construct($message, $this->errorCode, $previous);
    }


    public function getHeaders()
    {
        return $this->headers;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }
}
