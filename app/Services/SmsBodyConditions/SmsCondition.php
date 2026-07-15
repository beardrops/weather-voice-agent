<?php

namespace App\Services\SmsBodyConditions;

interface SmsCondition
{
    public function isMet(float $temperature, string $conditionLabel, float $windSpeed): bool;

    public function getLine(float $temperature, string $conditionLabel, float $windSpeed, string $location): string;
}