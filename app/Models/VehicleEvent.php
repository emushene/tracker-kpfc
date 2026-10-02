<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleEvent extends Model
{
    protected $fillable = [
        'vehicle_id',
        'event_type',
        'latitude',
        'longitude',
        'speed',
        'event_time',
        'metadata',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'speed' => 'float',
        'event_time' => 'integer',
        'metadata' => 'array',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
