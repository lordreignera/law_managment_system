<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Gateway
    |--------------------------------------------------------------------------
    |
    | Supported: "africastalking", "log". The "log" gateway writes messages to
    | the application log and is the safe default for local/testing so no real
    | SMS is sent until credentials and the live gateway are configured.
    |
    */

    'default' => env('SMS_GATEWAY', 'log'),

    'log_channel' => env('SMS_LOG_CHANNEL'),

    'africastalking' => [
        'username' => env('AT_USERNAME', 'sandbox'),
        'api_key' => env('AT_API_KEY'),
        'sender_id' => env('AT_SENDER_ID'),
        'sandbox' => (bool) env('AT_SANDBOX', true),
    ],

];
