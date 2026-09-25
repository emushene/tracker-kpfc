<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehiclePosition extends Model
{
    protected $fillable = [
        'vehicle_id',
        'latitude',
        'longitude',
        'speed',
        'course',
        'battery',
        'mileage',
        'today_mileage',
        'odometer',
        'acc_status',
        'charge_status',
        'oil_power_status',
        'door_status',
        'defence_status',
        'data_status',
        'fuel',
        'external_power',
        'heart_time',
        'gps_time',
        'server_time',
        'system_time',
        'temperature',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'speed' => 'float',
        'course' => 'float',
        'battery' => 'float',
        'temperature' => 'array',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
