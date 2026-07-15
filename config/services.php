<?php

return [
    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'phone_number' => env('TWILIO_PHONE_NUMBER'),
    ],

    'weather' => [
        'cold_threshold_celsius' => env('COLD_THRESHOLD_CELSIUS', 10),
    ],

    'email' => [
        'to_address' => env('EMAIL_TO_ADDRESS'),
    ],
];