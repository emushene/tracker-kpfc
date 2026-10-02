<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Shop extends Model
{
    protected $fillable = [
        'id',
        'name',
        'code',
        'address',
        'latitude',
        'longitude',
        'radius_meters',
        'active',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius_meters' => 'integer',
        'active' => 'boolean',
    ];

    public function vehicles(): HasMany
    {
        return $this->hasMany(
            Vehicle::class,
            'assigned_shop_id'
        );
    }

    public function deployments(): MorphMany
    {
        return $this->morphMany(
            VehicleDeployment::class,
            'destination'
        );
    }
}
