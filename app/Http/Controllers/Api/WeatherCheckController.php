<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SMSService;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeatherCheckController extends Controller
{
    public function __construct(
        private readonly WeatherService $weatherService,
        private readonly SMSService $smsService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city' => 'required|string|min:1',
            'country' => 'nullable|string',
            'callerPhone' => 'nullable|string',
        ]);

        $city = trim($validated['city']);
        $country = $validated['country'] ?? null;
        $callerPhone = $validated['callerPhone'] ?? null;

        $geo = $this->weatherService->geocodeCity($city, $country);

        if (!$geo['found']) {
            if ($geo['reason'] === 'not_found') {
                return response()->json([
                    'error' => 'city_not_found',
                    'message' => "I couldn't find a city called \"{$city}\". Could you try a different name or include a country?",
                ], 404);
            }

            if ($geo['reason'] === 'ambiguous') {
                $labels = array_map(
                    fn($o) => trim("{$o['name']}, {$o['admin']} {$o['country']}"),
                    array_slice($geo['options'], 0, 3)
                );

                return response()->json([
                    'error' => 'ambiguous_city',
                    'message' => 'There are several places called "' . $city . '": ' . implode('; ', $labels) . '. Could you be more specific?',
                    'options' => array_slice($geo['options'], 0, 3),
                ], 400);
            }
        }

        $weather = $this->weatherService->getCurrentWeather($geo['latitude'], $geo['longitude']);

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

        return response()->json([
            'temperature' => $weather['temperature'],
            'unit' => $weather['unit'],
            'condition' => $weather['conditionLabel'],
            'windSpeed' => $weather['windSpeed'],
            'coldAlertSent' => $coldAlertSent,
            'location' => $location,
            'message' => $message,
        ]);
    }
}