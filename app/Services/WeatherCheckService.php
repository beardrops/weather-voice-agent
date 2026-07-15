<?php

namespace App\Services;

use App\ValueObjects\WeatherCheckResult;

class WeatherCheckService
{
    public function __construct(
        private readonly WeatherService $weatherService,
        private readonly SMSService $smsService,
        private readonly EmailService $emailService,
        private readonly SmsBodyBuilder $smsBodyBuilder,
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

        $body = $this->smsBodyBuilder->build(
            temperature: $weather['temperature'],
            conditionLabel: $weather['conditionLabel'],
            windSpeed: $weather['windSpeed'],
            location: $geo['fullName'],
        );

        if ($callerPhone) {
            $this->smsService->send($body, $callerPhone);
        }

        $this->emailService->send($body);

        return new WeatherCheckResult(
            data: [
                'temperature' => $weather['temperature'],
                'unit' => $weather['unit'],
                'condition' => $weather['conditionLabel'],
                'windSpeed' => $weather['windSpeed'],
                'location' => $geo['fullName'],
            ],
            status: 200,
        );
    }
}