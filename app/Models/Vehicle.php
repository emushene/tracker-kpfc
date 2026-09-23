<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'assigned_shop_id',
        'road_distance_meters',
        'road_duration_seconds',
        'route_calculated_at',
        'route_latitude',
        'route_longitude',
        'route_destination_type',
        'route_destination_id',
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
        'road_distance_meters' => 'integer',
        'road_duration_seconds' => 'integer',
        'route_calculated_at' => 'datetime',
        'route_latitude' => 'float',
        'route_longitude' => 'float',
        'route_destination_id' => 'integer',
    ];

    /**
     * Retrieve the model for a bound value.
     *
     * Supports resolving by numeric ID, plate number (including with/without spaces and hyphens), or IMEI.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        if (is_numeric($value)) {
            $vehicle = $this->where('id', $value)->first();
            if ($vehicle !== null) {
                return $vehicle;
            }
        }

        $normalizedPlate = str_replace([' ', '-'], '', (string) $value);

        return $this->where('plate_number', $value)
            ->orWhereRaw("REPLACE(REPLACE(plate_number, ' ', ''), '-', '') = ?", [$normalizedPlate])
            ->orWhere('imei', (string) $value)
            ->first();
    }

    public function assignedShop(): BelongsTo
    {
        return $this->belongsTo(
            Shop::class,
            'assigned_shop_id'
        );
    }

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

    public function deployments(): HasMany
    {
        return $this->hasMany(
            VehicleDeployment::class
        );
    }
}
