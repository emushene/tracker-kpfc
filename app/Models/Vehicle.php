<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = [
        'imei',
        'device_name',
        'plate_number',
        'device_type',
        'simcard',
        'iccid',
        'activated_at',
        'online_at',
        'platform_due_at',
        'last_position_at',
        'active',
        'location_name',
        'location_latitude',
        'location_longitude',
        'location_updated_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'online_at' => 'datetime',
        'platform_due_at' => 'datetime',
        'last_position_at' => 'datetime',
        'active' => 'boolean',
        'location_latitude' => 'float',
        'location_longitude' => 'float',
        'location_updated_at' => 'datetime',
    ];

    public function positions(): HasMany
    {
        return $this->hasMany(VehiclePosition::class);
    }

    public function alarms(): HasMany
    {
        return $this->hasMany(VehicleAlarm::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(VehicleEvent::class);
    }

    public function mileage(): HasMany
    {
        return $this->hasMany(VehicleMileage::class);
    }

    public function playbacks(): HasMany
    {
        return $this->hasMany(VehiclePlayback::class);
    }
}
