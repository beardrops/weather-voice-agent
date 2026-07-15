<?php

namespace App\Exceptions;

use Exception;

class WeatherException extends Exception
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $httpStatus = 500,
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}