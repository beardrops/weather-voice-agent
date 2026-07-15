<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WeatherCheckController;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'php_version' => PHP_VERSION,
    ]);
});

Route::post('/weather-check', [WeatherCheckController::class, '__invoke']);