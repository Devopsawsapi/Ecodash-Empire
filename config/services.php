<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // ANTHROPIC / CLAUDE AI
    // Clé : https://console.anthropic.com/
    'anthropic' => [
        'key'   => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
    ],

    // IQAIR — Qualité air temps réel
    // Clé : https://www.iqair.com/dashboard/api
    'iqair' => [
        'key' => env('IQAIR_API_KEY'),
    ],

    // OPENWEATHERMAP — Air pollution par GPS
    // Clé : https://openweathermap.org/api/air-pollution
    'openweather' => [
        'key' => env('OPENWEATHER_API_KEY'),
    ],

    // WRI AQUEDUCT — Stress hydrique mondial (optionnel)
    'wri' => [
        'key' => env('WRI_API_KEY', null),
    ],

    // IUCN RED LIST — Statuts conservation espèces
    // Clé : https://apiv3.iucnredlist.org/
    'iucn' => [
        'key' => env('IUCN_API_KEY'),
    ],

];
