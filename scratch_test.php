<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$clientId = env('KPFC_SSO_CLIENT_ID');
$clientSecret = env('KPFC_SSO_CLIENT_SECRET');

echo "Testing with Client ID: $clientId\n";

$res = Http::asForm()
    ->withBasicAuth($clientId, $clientSecret)
    ->post('https://admin-staging.kpfcbuilders.com/oauth/token', [
        'grant_type' => 'client_credentials',
        'scope' => 'fleet:branches',
    ]);

echo 'Status: '.$res->status()."\n";
echo 'Body: '.$res->body()."\n";
