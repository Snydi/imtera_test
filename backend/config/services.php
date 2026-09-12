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

    'yandex_maps' => [
        'allowed_hosts' => [
            'yandex.ru',
            'yandex.com',
            'yandex.by',
            'yandex.kz',
            'yandex.uz',
            'yandex.com.tr',
            'yandex.pl',
            'www.yandex.ru',
            'www.yandex.com',
            'www.yandex.pl',
        ],
        'connect_timeout' => env('YANDEX_MAPS_CONNECT_TIMEOUT', 15),
        'timeout' => env('YANDEX_MAPS_TIMEOUT', 60),
        'max_pages' => env('YANDEX_MAPS_MAX_PAGES', 100),
        'page_delay_ms' => env('YANDEX_MAPS_PAGE_DELAY_MS', 350),
        'user_agent' => env('YANDEX_MAPS_USER_AGENT', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/140.0.0.0 Safari/537.36'),
    ],

];
