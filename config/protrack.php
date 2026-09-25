<?php

return [

    'base_url' => env(
        'PROTRACK_BASE_URL',
        'https://api.protrack365.com'
    ),

    /*
    |--------------------------------------------------------------------------
    | Protrack API Authentication
    |--------------------------------------------------------------------------
    |
    | These credentials are used to authenticate with Protrack365.
    |
    */

    'account' => env('PROTRACK_ACCOUNT'),

    'password' => env('PROTRACK_PASSWORD'),

    'timeout' => 30,

    /*
    |--------------------------------------------------------------------------
    | Vehicle Account
    |--------------------------------------------------------------------------
    |
    | This is the Protrack customer account whose vehicles this
    | application will monitor.
    |
    */

    'vehicle_account' => env(
        'PROTRACK_VEHICLE_ACCOUNT',
        'kpfctrack1'
    ),

];
