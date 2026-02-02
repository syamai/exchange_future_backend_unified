<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception thrown when circuit breaker is open.
 */
class CircuitOpenException extends Exception
{
    public function __construct(string $message = "Circuit breaker is open", int $code = 503, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
