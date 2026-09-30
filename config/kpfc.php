<?php

return [
    'admin' => [
        'url' => env('KPFC_ADMIN_URL', 'https://admin-staging.kpfcbuilders.com'),
        
        'branches' => [
            'client_id' => env('KPFC_BRANCH_CLIENT_ID'),
            'client_secret' => env('KPFC_BRANCH_CLIENT_SECRET'),
        ],
        
        'suppliers' => [
            'client_id' => env('KPFC_SUPPLIER_CLIENT_ID'),
            'client_secret' => env('KPFC_SUPPLIER_CLIENT_SECRET'),
        ],
    ],
];
