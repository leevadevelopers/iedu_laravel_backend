<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM_NUMBER'),
        'verify_service_sid' => env('TWILIO_VERIFY_SERVICE_SID'),
    ],

    'sendgrid' => [
        'api_key' => env('SENDGRID_API_KEY'),
        'from_email' => env('MAIL_FROM_ADDRESS', 'noreply@iedu.com'),
        'from_name' => env('MAIL_FROM_NAME', 'iEDU'),
    ],

    'external_gps' => [
        'api_key' => env('EXTERNAL_GPS_API_KEY'),
        'base_url' => env('EXTERNAL_GPS_BASE_URL'),
        'timeout' => env('EXTERNAL_GPS_TIMEOUT', 30),
        'cache_max_age' => env('EXTERNAL_GPS_CACHE_MAX_AGE', 30),
    ],

];
