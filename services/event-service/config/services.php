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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'auth' => [
        'base_url' => env('AUTH_SERVICE_URL', 'http://127.0.0.1:8000/api'),
    ],

    'halls' => [
        'base_url' => env('HALLS_SERVICE_URL', 'http://127.0.0.1:8002/api'),
    ],

    'booking' => [
        'base_url' => env('BOOKING_SERVICE_URL', 'http://127.0.0.1:8003/api'),
    ],

    'recommendations' => [
        'base_url' => env('RECOMMENDATION_SERVICE_URL', 'http://127.0.0.1:8004/api'),
    ],

    'concierge' => [
        'provider' => env('EVENT_ASSISTANT_PROVIDER', 'openai'),
        'model' => env('EVENT_ASSISTANT_MODEL'),
        'timeout' => (int) env('EVENT_ASSISTANT_TIMEOUT', 30),
        'candidate_limit' => (int) env('EVENT_ASSISTANT_CANDIDATE_LIMIT', 12),
    ],

    'internal' => [
        'api_key' => env('INTERNAL_API_KEY', 'submeet-internal-key'),
    ],

];
