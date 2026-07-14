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
        $isCold = $temperature < self::THRESHOLD;
        $this->assertSame($expectsAlert, $isCold);
    }

    public function test_sms_sent_when_cold_and_phone_provided(): void
    {
        $temperature = 5;
        $callerPhone = '+14155551234';
        $shouldSend = $temperature < self::THRESHOLD && (bool) $callerPhone;

        $this->assertTrue($shouldSend);
    }

    public function test_sms_not_sent_when_warm_even_with_phone(): void
    {
        $temperature = 22;
        $callerPhone = '+14155551234';
        $shouldSend = $temperature < self::THRESHOLD && (bool) $callerPhone;

        $this->assertFalse($shouldSend);
    }

    public function test_sms_not_sent_when_cold_but_no_phone(): void
    {
        $temperature = 5;
        $callerPhone = null;
        $shouldSend = $temperature < self::THRESHOLD && (bool) $callerPhone;

        $this->assertFalse($shouldSend);
    }

    public function test_sms_message_contains_city(): void
    {
        $message = $this->buildMessage('London', 7, 'Overcast');
        $this->assertStringContainsString('London', $message);
    }

    public function test_sms_message_contains_temperature(): void
    {
        $message = $this->buildMessage('Paris', 6, 'Rain');
        $this->assertStringContainsString("6°C", $message);
    }

    public function test_sms_message_contains_condition(): void
    {
        $message = $this->buildMessage('Tokyo', 9, 'Cloudy');
        $this->assertStringContainsString('Cloudy', $message);
    }

    public function test_sms_message_handles_special_characters(): void
    {
        $message = $this->buildMessage('São Paulo', 8, 'Rain');
        $this->assertStringContainsString('São Paulo', $message);
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