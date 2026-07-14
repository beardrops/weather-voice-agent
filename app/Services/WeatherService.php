<?php

namespace App\Services;

use App\Exceptions\WeatherException;

class WeatherService
{
    private const GEOCODING_BASE = 'https://geocoding-api.open-meteo.com/v1/search';
    private const WEATHER_BASE = 'https://api.open-meteo.com/v1/forecast';

    /**
     * @return array{found: bool, latitude?: float, longitude?: float, fullName?: string, reason?: string, options?: array}
     */
    public function geocodeCity(string $city, ?string $country = null): array
    {
        $params = http_build_query([
            'name' => $city,
            'count' => 5,
            'language' => 'en',
            'format' => 'json',
        ]);

        if ($country) {
            $params .= '&country=' . urlencode($country);
        }

        $url = self::GEOCODING_BASE . '?' . $params;

        $response = $this->fetch($url);

        if (!$response || !isset($response['results'])) {
            return ['found' => false, 'reason' => 'not_found'];
        }

        $results = $response['results'];

        if (count($results) === 0) {
            return ['found' => false, 'reason' => 'not_found'];
        }

        if (count($results) === 1) {
            $r = $results[0];
            $parts = array_filter([$r['name'], $r['admin1'] ?? '', $r['country_code'] ?? '']);
            return [
                'found' => true,
                'latitude' => $r['latitude'],
                'longitude' => $r['longitude'],
                'fullName' => implode(', ', $parts),
            ];
        }

        $options = array_map(fn($r) => [
            'name' => $r['name'],
            'admin' => $r['admin1'] ?? '',
            'country' => $r['country_code'] ?? '',
            'latitude' => $r['latitude'],
            'longitude' => $r['longitude'],
        ], $results);

        return ['found' => false, 'reason' => 'ambiguous', 'options' => $options];
    }

    /**
     * @return array{temperature: float, unit: string, condition: string, conditionLabel: string, windSpeed: float}
     */
    public function getCurrentWeather(float $latitude, float $longitude): array
    {
        $params = http_build_query([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current_weather' => 'true',
            'temperature_unit' => 'celsius',
            'timezone' => 'auto',
        ]);

        $url = self::WEATHER_BASE . '?' . $params;
        $response = $this->fetch($url);

        if (!$response || !isset($response['current_weather'])) {
            throw new WeatherException('service_error', 'Weather API returned an unexpected response.', 502);
        }

        $current = $response['current_weather'];

        return [
            'temperature' => $current['temperature'],
            'unit' => 'celsius',
            'condition' => $current['weathercode'],
            'conditionLabel' => $this->mapWeatherCode($current['weathercode']),
            'windSpeed' => $current['windspeed'],
        ];
    }

    private function fetch(string $url): ?array
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'GET',
                'header' => "Accept: application/json\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            return null;
        }

        return json_decode($body, true);
    }

    private function mapWeatherCode(int $code): string
    {
        return match ($code) {
            0 => 'Clear sky',
            1 => 'Mainly clear',
            2 => 'Partly cloudy',
            3 => 'Overcast',
            45, 48 => 'Foggy',
            51 => 'Light drizzle',
            53 => 'Moderate drizzle',
            55 => 'Dense drizzle',
            61 => 'Slight rain',
            63 => 'Moderate rain',
            65 => 'Heavy rain',
            71 => 'Slight snow',
            73 => 'Moderate snow',
            75 => 'Heavy snow',
            77 => 'Snow grains',
            80 => 'Slight rain showers',
            81 => 'Moderate rain showers',
            82 => 'Violent rain showers',
            85 => 'Slight snow showers',
            86 => 'Heavy snow showers',
            95 => 'Thunderstorm',
            96, 99 => 'Thunderstorm with hail',
            default => "Unknown (code {$code})",
        };
    }
}