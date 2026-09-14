<?php

return [

    'base_url' => env(
        'PROTRACK_BASE_URL',
        'https://api.protrack365.com'
    ),

    'account' => env('PROTRACK_ACCOUNT'),

    'password' => env('PROTRACK_PASSWORD'),

    'timeout' => 30,

];