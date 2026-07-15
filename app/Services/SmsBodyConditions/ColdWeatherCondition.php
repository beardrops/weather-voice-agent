<?php

namespace App\Services\SmsBodyConditions;

class ColdWeatherCondition implements SmsCondition
{
    public function __construct(
        private readonly float $threshold,
    ) {}

    public function isMet(float $temperature, string $conditionLabel, float $windSpeed): bool
    {
        return $temperature < $this->threshold;
    }

    public function getLine(float $temperature, string $conditionLabel, float $windSpeed, string $location): string
    {
        return "🧥 Cold weather alert! It's {$temperature}°C in {$location} ({$conditionLabel}). Don't forget your coat!";
    }
}