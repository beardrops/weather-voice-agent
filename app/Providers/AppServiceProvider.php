<?php

namespace App\Providers;

use App\Services\SmsBodyBuilder;
use App\Services\SmsBodyConditions\ColdWeatherCondition;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsBodyBuilder::class, function () {
            return new SmsBodyBuilder([
                new ColdWeatherCondition(
                    threshold: (float) config('services.weather.cold_threshold_celsius', 10),
                ),
            ]);
        });
    }

    public function boot(): void
    {
        //
    }
}