<?php

namespace App\Services;

use App\ValueObjects\WeatherCheckResult;

class WeatherCheckService
{
    public function __construct(
        private readonly WeatherService $weatherService,
        private readonly SMSService $smsService,
    ) {}

    public function check(string $city, ?string $country, ?string $callerPhone): WeatherCheckResult
    {
        $geo = $this->weatherService->geocodeCity($city, $country);

        if ($geo['found'] === false) {
            if ($geo['reason'] === 'not_found') {
                return new WeatherCheckResult(
                    data: [
                        'error' => 'city_not_found',
                        'message' => "I couldn't find a city called \"{$city}\". Could you try a different name or include a country?",
                    ],
                    status: 404,
                );
            }

            if ($geo['reason'] === 'ambiguous') {
                $labels = array_map(
                    fn($o) => trim("{$o['name']}, {$o['admin']} {$o['country']}"),
                    array_slice($geo['options'], 0, 3),
                );

                return new WeatherCheckResult(
                    data: [
                        'error' => 'ambiguous_city',
                        'message' => 'There are several places called "' . $city . '": ' . implode('; ', $labels) . '. Could you be more specific?',
                        'options' => array_slice($geo['options'], 0, 3),
                    ],
                    status: 400,
                );
            }
        }

        $weather = $this->weatherService->getCurrentWeather($geo['latitude'], $geo['longitude']);

        logger()->info('Weather API response', [
            'location' => $geo['fullName'],
            'temperature' => $weather['temperature'],
            'condition' => $weather['conditionLabel'],
            'windSpeed' => $weather['windSpeed'],
        ]);

        $threshold = (float) config('services.weather.cold_threshold_celsius', 10);
        $coldAlertSent = false;

        if ($weather['temperature'] < $threshold && $callerPhone) {
            $result = $this->smsService->sendColdAlert(
                $geo['fullName'],
                $weather['temperature'],
                $weather['conditionLabel'],
                $callerPhone,
            );
            $coldAlertSent = $result['sent'];
        }

        $location = $geo['fullName'];

        if ($coldAlertSent) {
            $message = "The temperature in {$location} is {$weather['temperature']}°C with " . strtolower($weather['conditionLabel']) . ". A cold weather alert has been sent to your phone.";
        } elseif ($weather['temperature'] < $threshold) {
            $message = "The temperature in {$location} is {$weather['temperature']}°C with " . strtolower($weather['conditionLabel']) . ". It's cold out there! Please provide a phone number so I can send you a coat reminder.";
        } else {
            $message = "The temperature in {$location} is {$weather['temperature']}°C with " . strtolower($weather['conditionLabel']) . ". Nice weather — no coat needed today!";
        }

        return new WeatherCheckResult(
            data: [
                'temperature' => $weather['temperature'],
                'unit' => $weather['unit'],
                'condition' => $weather['conditionLabel'],
                'windSpeed' => $weather['windSpeed'],
                'coldAlertSent' => $coldAlertSent,
                'location' => $location,
                'message' => $message,
            ],
            status: 200,
        );
    }
}