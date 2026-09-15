<?php

namespace App\Providers;

use App\Models\Location;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'shop' => Shop::class,
            'location' => Location::class,
        ]);
    }
}