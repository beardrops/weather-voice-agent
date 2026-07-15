<?php

namespace App\Services;

use App\Services\SmsBodyConditions\SmsCondition;

class SmsBodyBuilder
{
    private const WEATHER_LINE = "📍 Weather in %s: %.1f°C, %s (Wind: %.1f km/h)";

    /** @param SmsCondition[] $conditions */
    public function __construct(
        private readonly array $conditions,
    ) {}

    public function build(float $temperature, string $conditionLabel, float $windSpeed, string $location): string
    {
        $lines = [
            sprintf(self::WEATHER_LINE, $location, $temperature, $conditionLabel, $windSpeed),
        ];

        foreach ($this->conditions as $condition) {
            if ($condition->isMet($temperature, $conditionLabel, $windSpeed)) {
                $lines[] = $condition->getLine($temperature, $conditionLabel, $windSpeed, $location);
            }
        }

        return implode("\n", $lines);
    }
}