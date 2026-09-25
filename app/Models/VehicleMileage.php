<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleMileage extends Model
{
    protected $table = 'vehicle_mileage';

    protected $fillable = [
        'vehicle_id',
        'date',
        'mileage',
        'odometer',
    ];

    protected $casts = [
        'date' => 'date',
        'mileage' => 'integer',
        'odometer' => 'integer',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
