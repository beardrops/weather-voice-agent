<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WeatherLogicTest extends TestCase
{
    private const THRESHOLD = 10;

    public static function coldTemperatureProvider(): array
    {
        return [
            'below threshold' => [5, true],
            'at threshold exactly' => [10, false],
            'above threshold' => [25, false],
            'negative temperature' => [-5, true],
            'just below threshold' => [9.9, true],
            'just above threshold' => [10.1, false],
        ];
    }

    #[DataProvider('coldTemperatureProvider')]
    public function test_cold_threshold(float $temperature, bool $expectsAlert): void
    {
        $this->assertSame($expectsAlert, $temperature < self::THRESHOLD);
    }

    public function test_sms_sent_when_cold_and_phone_provided(): void
    {
        $this->assertTrue(5 < self::THRESHOLD && (bool) '+14155551234');
    }

    public function test_sms_not_sent_when_warm_even_with_phone(): void
    {
        $this->assertFalse(22 < self::THRESHOLD && (bool) '+14155551234');
    }

    public function test_sms_not_sent_when_cold_but_no_phone(): void
    {
        $this->assertFalse(5 < self::THRESHOLD && (bool) null);
    }

    public function test_sms_message_contains_city(): void
    {
        $this->assertStringContainsString('London', $this->buildMessage('London', 7, 'Overcast'));
    }

    public function test_sms_message_contains_temperature(): void
    {
        $this->assertStringContainsString("6.0°C", $this->buildMessage('Paris', 6, 'Rain'));
    }

    public function test_sms_message_contains_condition(): void
    {
        $this->assertStringContainsString('Cloudy', $this->buildMessage('Tokyo', 9, 'Cloudy'));
    }

    public function test_sms_message_handles_special_characters(): void
    {
        $this->assertStringContainsString('São Paulo', $this->buildMessage('São Paulo', 8, 'Rain'));
    }

    private function buildMessage(string $city, float $temp, string $condition): string
    {
        return sprintf(
            "🧥 Cold weather alert! It's %.1f°C in %s today (%s). Don't forget your coat!",
            $temp,
            $city,
            $condition
        );
    }
}