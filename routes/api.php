<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WeatherCheckController;

Route::post('/api/weather-check', [WeatherCheckController::class, '__invoke']);