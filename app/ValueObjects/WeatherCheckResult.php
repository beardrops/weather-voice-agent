<?php

namespace App\ValueObjects;

class WeatherCheckResult
{
    public function __construct(
        public readonly array $data,
        public readonly int $status = 200,
    ) {}
}