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

    'bunny' => [
        'stream_api_key' => env('BUNNY_STREAM_API_KEY'),
        'stream_library_id' => env('BUNNY_STREAM_LIBRARY_ID'),
        'stream_pull_zone' => env('BUNNY_STREAM_PULL_ZONE'),
    ],

    'ip-address-resolver' => [
        'url' => env('IP_ADDRESS_RESOLVER_URL', 'http://ip-api.com/batch'),
        'call_limit_per_minute' => env('IP_ADDRESS_RESOLVER_CALL_LIMIT_PER_MINUTE', 15),
        'max_ip_addresses_per_call' => env('IP_ADDRESS_RESOLVER_MAX_IP_ADDRESSES_PER_CALL', 100),
    ],
];
