<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;

class ShopController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Shop::query()
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
                ]),
        ]);
    }
}
