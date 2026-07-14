<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WeatherCheckValidationTest extends TestCase
{
    #[Test]
    public function it_rejects_empty_body(): void
    {
        $response = $this->postJson('/api/weather-check', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['city']);
    }

    #[Test]
    public function it_rejects_missing_city(): void
    {
        $response = $this->postJson('/api/weather-check', [
            'callerPhone' => '+123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['city']);
    }

    #[Test]
    public function it_rejects_empty_string_city(): void
    {
        $response = $this->postJson('/api/weather-check', [
            'city' => '   ',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['city']);
    }

    #[Test]
    public function it_accepts_valid_city(): void
    {
        // This will hit the real API if no mocking is set up.
        // We just validate it passes validation (won't 422).
        $response = $this->postJson('/api/weather-check', [
            'city' => 'London',
            'callerPhone' => '+123',
        ]);

        $this->assertNotEquals(422, $response->status());
    }

    #[Test]
    public function it_accepts_city_with_country(): void
    {
        $response = $this->postJson('/api/weather-check', [
            'city' => 'London',
            'country' => 'UK',
            'callerPhone' => '+123',
        ]);

        $this->assertNotEquals(422, $response->status());
    }
}