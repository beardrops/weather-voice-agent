<?php

namespace Tests\Unit;

use App\Services\SmsBodyBuilder;
use App\Services\SmsBodyConditions\ColdWeatherCondition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WeatherLogicTest extends TestCase
{
    private const THRESHOLD = 10;

    // ── ColdWeatherCondition threshold tests ──

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
    public function test_cold_condition_is_met(float $temperature, bool $expectsMet): void
    {
        $condition = new ColdWeatherCondition(self::THRESHOLD);
        $this->assertSame($expectsMet, $condition->isMet($temperature, 'Clear sky', 10));
    }

    public function test_cold_condition_line_contains_city(): void
    {
        $condition = new ColdWeatherCondition(self::THRESHOLD);
        $line = $condition->getLine(7, 'Overcast', 10, 'London');
        $this->assertStringContainsString('London', $line);
    }

    public function test_cold_condition_line_contains_temperature(): void
    {
        $condition = new ColdWeatherCondition(self::THRESHOLD);
        $line = $condition->getLine(6, 'Rain', 10, 'Paris');
        $this->assertStringContainsString('6°C', $line);
    }

    // ── SmsBodyBuilder tests ──

    public function test_builder_always_includes_weather_line(): void
    {
        $builder = new SmsBodyBuilder([]);
        $body = $builder->build(22, 'Clear sky', 5, 'Tokyo');
        $this->assertStringContainsString('📍 Weather in Tokyo', $body);
        $this->assertStringContainsString('22.0°C', $body);
        $this->assertStringContainsString('Clear sky', $body);
    }

    public function test_builder_includes_met_condition_line(): void
    {
        $condition = new ColdWeatherCondition(10);
        $builder = new SmsBodyBuilder([$condition]);
        $body = $builder->build(5, 'Overcast', 10, 'London');
        $this->assertStringContainsString('🧥 Cold weather alert', $body);
    }

    public function test_builder_skips_unmet_condition(): void
    {
        $condition = new ColdWeatherCondition(10);
        $builder = new SmsBodyBuilder([$condition]);
        $body = $builder->build(22, 'Clear sky', 5, 'Tokyo');
        $this->assertStringNotContainsString('Cold weather alert', $body);
    }

    public function test_builder_handles_special_characters(): void
    {
        $builder = new SmsBodyBuilder([]);
        $body = $builder->build(8, 'Rain', 10, 'São Paulo');
        $this->assertStringContainsString('São Paulo', $body);
    }

    public function test_builder_with_no_conditions_still_outputs_weather(): void
    {
        $builder = new SmsBodyBuilder([]);
        $body = $builder->build(15, 'Partly cloudy', 8, 'Berlin');
        $this->assertStringContainsString('Weather in Berlin', $body);
        $this->assertStringContainsString('15.0°C', $body);
        $this->assertStringContainsString('Partly cloudy', $body);
    }
}