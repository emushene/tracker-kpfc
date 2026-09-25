<?php

namespace App\Console\Commands;

use App\Services\ShopLocationService;
use Illuminate\Console\Command;

class TestShopLocation extends Command
{
    protected $signature = 'shop:test-location';

    protected $description = 'Test shop location matching';

    public function handle(ShopLocationService $shopLocationService): int
    {
        // Main Shop coordinates
        // $latitude = -1.2406854;
        // $longitude = 36.6652482;
        $latitude = -1.2406854;
        $longitude = 36.6652482;

        $shop = $shopLocationService->findNearbyShop(
            $latitude,
            $longitude
        );

        if (! $shop) {
            $this->error('No shop found.');

            return self::FAILURE;
        }

        $this->info('Shop found: '.$shop->name);
        $this->info('Address: '.$shop->address);
        $this->info('Radius: '.$shop->radius_meters.' meters');

        return self::SUCCESS;
    }
}
