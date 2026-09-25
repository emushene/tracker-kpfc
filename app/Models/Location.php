<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = [
        'name',
        'type',
        'address',
        'latitude',
        'longitude',
        'active',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'active' => 'boolean',
    ];

    public function deployments(): HasMany
    {
        return $this->hasMany(
            VehicleDeployment::class,
            'destination_location_id'
        );
    }
}
