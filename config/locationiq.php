<?php

return [

    'base_url' => env(
        'LOCATIONIQ_BASE_URL',
        'https://us1.locationiq.com'
    ),

    'api_key' => env('LOCATIONIQ_API_KEY'),

    'timeout' => 15,

];
