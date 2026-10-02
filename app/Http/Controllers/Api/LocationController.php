<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    /**
     * List all active client locations, distribution hubs, and customer depots.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Location::query()
                ->where('active', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'type',
                    'address',
                    'latitude',
                    'longitude',
                ]),
        ]);
    }

    /**
     * List all destinations (both internal branch shops and client locations) categorized for fleet dispatch.
     */
    public function destinations(): JsonResponse
    {
        $shops = Shop::query()
            ->where('active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'code',
                'address',
                'latitude',
                'longitude',
                'radius_meters',
            ])
            ->map(fn ($shop) => [
                'type' => 'shop',
                'id' => $shop->id,
                'name' => $shop->name,
                'code' => $shop->code,
                'address' => $shop->address,
                'latitude' => (float) $shop->latitude,
                'longitude' => (float) $shop->longitude,
                'category' => 'Internal Branches & Shops',
            ]);

        $locations = Location::query()
            ->where('active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'type',
                'address',
                'latitude',
                'longitude',
            ])
            ->map(fn ($loc) => [
                'type' => 'location',
                'id' => $loc->id,
                'name' => $loc->name,
                'code' => strtoupper($loc->type),
                'address' => $loc->address,
                'latitude' => (float) $loc->latitude,
                'longitude' => (float) $loc->longitude,
                'category' => 'Customer Sites & Depots',
            ]);

        return response()->json([
            'data' => [
                'shops' => $shops,
                'locations' => $locations,
                'all' => $shops->concat($locations)->values(),
            ],
        ]);
    }
}
