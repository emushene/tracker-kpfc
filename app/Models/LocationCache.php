<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationCache extends Model
{
    protected $table = 'location_cache';

    protected $fillable = [
        'latitude',
        'longitude',
        'latitude_bucket',
        'longitude_bucket',
        'location_name',
        'address',
        'provider',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'latitude_bucket' => 'float',
        'longitude_bucket' => 'float',
    ];

    public static function bucket(
        float $latitude,
        float $longitude
    ): array {
        return [
            'latitude_bucket' => round($latitude, 4),
            'longitude_bucket' => round($longitude, 4),
        ];
    }
}
