<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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
    'google' => [
        'sheets' => [
            'spreadsheet_id' => env('GOOGLE_SHEETS_SPREADSHEET_ID'),
            'credentials' => base_path(env('GOOGLE_SHEETS_CREDENTIALS')),
        ],
    ],

    'kpfc_sso' => [
        'issuer' => env('KPFC_SSO_ISSUER', 'https://admin-staging.kpfcbuilders.com'),
        'client_id' => env('KPFC_SSO_CLIENT_ID'),
        'client_secret' => env('KPFC_SSO_CLIENT_SECRET'),
        'redirect_uri' => env('KPFC_SSO_REDIRECT_URI'),
        'scopes' => env('KPFC_SSO_SCOPES', 'fleet:login fleet:profile'),
        'webhook_secret' => env('KPFC_SSO_WEBHOOK_SECRET'),
        'webhook_secret_old' => env('KPFC_SSO_WEBHOOK_SECRET_OLD'),
    ],

];
