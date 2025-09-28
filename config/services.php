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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'tavily' => [
        'api_key' => env('TAVILY_API_KEY'),
        'base_url' => env('TAVILY_BASE_URL', 'https://api.tavily.com'),
        'timeout' => env('TAVILY_TIMEOUT', 15),
    ],

    'x' => [
        'api_key' => env('X_API_KEY'),
        'api_secret' => env('X_API_SECRET'),
        'auth_url' => env('X_API_AUTH_URL', 'https://api.x.com/oauth2/token'),
        'base_url' => env('X_API_BASE_URL', 'https://api.x.com/2'),
        'timeout' => env('X_API_TIMEOUT', 15),
        'default_max_results' => env('X_API_DEFAULT_MAX_RESULTS', 20),
    ],

];
