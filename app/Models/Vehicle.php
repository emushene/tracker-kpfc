<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Vehicle extends Model
{
    use HasFactory;

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
     * Resolve a vehicle from a route parameter by ID, plate number, or IMEI.
     *
     * This lets URL parameters like /vehicles/109 or /vehicles/KBZ-001Q resolve
     * to the correct vehicle record without extra controller logic.
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

    /**
     * Compute the current fleet status from live telemetry instead of storing it in the database.
     *
     * Priority order:
     * 1. active deployment => deployed
     * 2. non-zero speed => moving
     * 3. ignition on => idling
     * 4. active vehicle => parked
     * 5. otherwise => idle
     */
    protected function status(): Attribute
    {
        return new Attribute(
            get: function () {
                $position = $this->positions()->latest('gps_time')->first();

                if ($this->deployments()
                    ->whereIn('status', ['planned', 'dispatched', 'in_progress'])
                    ->exists()) {
                    return 'deployed';
                }

                if ($position && ($position->speed ?? 0) > 0) {
                    return 'moving';
                }

                if ($position && ($position->acc_status ?? 0) === 1) {
                    return 'idling';
                }

                return $this->active ? 'parked' : 'idle';
            }
        );
    }

    /**
     * Return the permanent home shop assigned to this vehicle.
     * Used for home-base routing, distance calculations, and operational assignment.
     */
    public function assignedShop(): BelongsTo
    {
        return $this->belongsTo(
            Shop::class,
            'assigned_shop_id'
        );
    }

    /**
     * Return all GPS/telemetry position records for this vehicle.
     * The latest record is used to determine movement, last location, and speed.
     */
    public function positions(): HasMany
    {
        return $this->hasMany(VehiclePosition::class);
    }

    /**
     * Return vehicle alarm events generated from telematics or system rules.
     */
    public function alarms(): HasMany
    {
        return $this->hasMany(VehicleAlarm::class);
    }

    /**
     * Return vehicle event records such as state changes, ingest updates, and system notices.
     */
    public function events(): HasMany
    {
        return $this->hasMany(VehicleEvent::class);
    }

    /**
     * Return mileage or odometer-related records for this vehicle.
     */
    public function mileage(): HasMany
    {
        return $this->hasMany(VehicleMileage::class);
    }

    /**
     * Return playback history records for route reconstruction or historical review.
     */
    public function playbacks(): HasMany
    {
        return $this->hasMany(VehiclePlayback::class);
    }

    /**
     * Return temporary or active deployments assigned to this vehicle.
     * These control routing, dispatch status, and destination mission logic.
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(
            VehicleDeployment::class
        );
    }

    public function maintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }

    public function maintenanceAlerts(): HasMany
    {
        return $this->hasMany(MaintenanceAlert::class);
    }

    public function maintenanceTickets(): HasMany
    {
        return $this->hasMany(MaintenanceTicket::class);
    }

    public function maintenanceJobCards(): HasMany
    {
        return $this->hasMany(MaintenanceJobCard::class);
    }

    public function repairs(): HasMany
    {
        return $this->hasMany(VehicleRepair::class);
    }

    public function replacements(): HasMany
    {
        return $this->hasMany(VehicleReplacement::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function activeTrip(): HasOne
    {
        return $this->hasOne(Trip::class)->whereIn('status', ['in_progress', 'returning_to_base'])->latestOfMany();
    }
}
